import { createHash } from "node:crypto";
import { readFile, writeFile } from "node:fs/promises";
import { resolve } from "node:path";

const root = resolve("popi-connector/contracts/v1");
const manifestPath = resolve(root, "manifest.json");
const manifest = JSON.parse(await readFile(manifestPath, "utf8"));
const sha256 = (bytes) => createHash("sha256").update(bytes).digest("hex");

for (const relative of Object.keys(manifest.files)) {
  manifest.files[relative] = sha256(await readFile(resolve(root, relative)));
}

await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, "utf8");
console.log(`Updated ${Object.keys(manifest.files).length} POPI Connector contract hashes`);
