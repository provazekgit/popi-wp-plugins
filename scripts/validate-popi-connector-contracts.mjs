import { createHash } from "node:crypto";
import { readFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";

const root = resolve("popi-connector/contracts/v1");
const parse = async (path) => JSON.parse(await readFile(path, "utf8"));
const sha256 = (bytes) => createHash("sha256").update(bytes).digest("hex");
const assert = (condition, message) => { if (!condition) throw new Error(message); };

const manifestPath = resolve(root, "manifest.json");
const manifest = await parse(manifestPath);
assert(manifest.contract === "popi-connector", "Unexpected contract bundle name");
assert(manifest.version === "1.0.0", "Unexpected contract bundle version");

for (const [relative, expected] of Object.entries(manifest.files)) {
  const bytes = await readFile(resolve(root, relative));
  assert(sha256(bytes) === expected, `Contract hash mismatch: ${relative}`);
  JSON.parse(bytes.toString("utf8"));
}

const documents = new Map();
for (const relative of Object.keys(manifest.files)) {
  documents.set(resolve(root, relative), await parse(resolve(root, relative)));
}

function pointer(document, fragment) {
  if (!fragment || fragment === "#") return document;
  return fragment.slice(2).split("/").reduce((value, part) => value[part.replaceAll("~1", "/").replaceAll("~0", "~")], document);
}

function validateRefs(value, sourcePath) {
  if (!value || typeof value !== "object") return;
  if (typeof value.$ref === "string") {
    const [relative, rawFragment] = value.$ref.split("#", 2);
    const targetPath = relative ? resolve(dirname(sourcePath), relative) : sourcePath;
    const target = documents.get(targetPath);
    assert(target, `Missing local contract reference ${value.$ref} in ${sourcePath}`);
    assert(pointer(target, rawFragment === undefined ? "" : `#${rawFragment}`) !== undefined, `Missing JSON pointer ${value.$ref} in ${sourcePath}`);
  }
  for (const child of Object.values(value)) validateRefs(child, sourcePath);
}
for (const [path, document] of documents) validateRefs(document, path);

const openapiPath = resolve(root, manifest.openapi);
const openapi = documents.get(openapiPath);
assert(openapi.openapi === "3.1.0", "OpenAPI must use version 3.1.0");
assert(openapi["x-popi-authentication"]?.protocol === "popi-hmac-v1", "OpenAPI must declare HMAC envelope authentication");

function dereferenceLocal(value) {
  if (!value?.$ref?.startsWith("#/")) return value;
  return pointer(openapi, value.$ref);
}

const documented = new Map();
for (const [path, rawPathItem] of Object.entries(openapi.paths)) {
  assert(!path.includes("{path}"), `Universal proxy path is forbidden: ${path}`);
  const pathItem = dereferenceLocal(rawPathItem);
  assert(pathItem.post, `Only explicit POST operations are supported: ${path}`);
  assert(!pathItem.delete, `DELETE operation is forbidden: ${path}`);
  const operation = pathItem.post;
  assert(operation.operationId && operation["x-popi-scope"] && operation["x-popi-payload-schema"], `Incomplete typed operation: ${path}`);
  assert(!documented.has(operation.operationId), `Duplicate operationId: ${operation.operationId}`);
  documented.set(operation.operationId, { path, scope: operation["x-popi-scope"] });
}

const restSource = await readFile(resolve("popi-connector/includes/class-rest-api.php"), "utf8");
const routePattern = /self::route\(\s*'([^']+)'\s*,\s*'([^']+)'/g;
const implemented = new Map();
for (const match of restSource.matchAll(routePattern)) {
  const route = match[1];
  const scope = match[2];
  const path = `/wp-json/popi-connector/v1${route}`;
  const entry = [...documented.entries()].find(([, item]) => item.path === path);
  assert(entry, `Implemented route is missing from OpenAPI: ${path}`);
  assert(entry[1].scope === scope, `Scope mismatch for ${path}: PHP=${scope}, OpenAPI=${entry[1].scope}`);
  implemented.set(entry[0], { path, scope });
}
assert(implemented.size === documented.size, `OpenAPI documents ${documented.size} operations, PHP implements ${implemented.size}`);

const gateway = documents.get(resolve(root, "schemas/gateway.json"));
const gatewayOperations = gateway.$defs.request.properties.operation.enum;
for (const operation of gatewayOperations) assert(documented.has(operation), `Gateway operation is absent from WordPress OpenAPI: ${operation}`);

const popisiteOpenapi = documents.get(resolve(root, manifest.popisite_openapi));
assert(popisiteOpenapi?.openapi === "3.1.0", "POPIsite OpenAPI must use version 3.1.0");
const popisiteOperationIds = new Set();
for (const [path, rawPathItem] of Object.entries(popisiteOpenapi.paths)) {
  assert(!path.includes("{path}"), `Universal POPIsite proxy path is forbidden: ${path}`);
  const pathItem = rawPathItem.$ref ? pointer(popisiteOpenapi, rawPathItem.$ref) : rawPathItem;
  for (const method of ["get", "post"]) {
    const operation = pathItem[method];
    if (!operation) continue;
    assert(operation.operationId && operation["x-popi-auth"], `Incomplete POPIsite operation: ${method.toUpperCase()} ${path}`);
    assert(!popisiteOperationIds.has(operation.operationId), `Duplicate POPIsite operationId: ${operation.operationId}`);
    popisiteOperationIds.add(operation.operationId);
  }
  assert(!pathItem.delete, `DELETE operation is forbidden: ${path}`);
}
assert(popisiteOperationIds.size === 12, `Expected 12 POPIsite operations, got ${popisiteOperationIds.size}`);

console.log(`POPI Connector contracts passed (${documented.size} WordPress and ${popisiteOperationIds.size} POPIsite operations, ${Object.keys(manifest.files).length} hashed files)`);
