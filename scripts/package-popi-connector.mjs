import { createHash } from "node:crypto";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import { spawnSync } from "node:child_process";
import { resolve } from "node:path";

const pluginPath = "popi-connector";
const pluginFile = `${pluginPath}/popi-connector.php`;
const outputDir = resolve("dist");
const archive = resolve(outputDir, "popi-connector.zip");

const dirty = spawnSync("git", ["status", "--porcelain", "--", pluginPath], { encoding: "utf8" });
if (dirty.status !== 0) throw new Error(dirty.stderr || "Unable to inspect POPI Connector source");
if (dirty.stdout.trim()) throw new Error("Commit POPI Connector changes before packaging them");

const source = await readFile(pluginFile, "utf8");
const headerVersion = source.match(/^ \* Version:\s*([^\s]+)$/m)?.[1];
const runtimeVersion = source.match(/define\( 'POPI_CONNECTOR_VERSION', '([^']+)' \);/)?.[1];
if (!headerVersion || headerVersion !== runtimeVersion) throw new Error("Plugin header and runtime versions do not match");

await mkdir(outputDir, { recursive: true });

function crc32(bytes) {
  let crc = 0xffffffff;
  for (const byte of bytes) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit += 1) crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1));
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function uint16(value) {
  const bytes = Buffer.alloc(2);
  bytes.writeUInt16LE(value);
  return bytes;
}

function uint32(value) {
  const bytes = Buffer.alloc(4);
  bytes.writeUInt32LE(value >>> 0);
  return bytes;
}

// Minimal ZIP writer using STORE, a fixed DOS timestamp and blobs read from
// Git. It is intentionally self-contained: the same commit produces exactly
// the same bytes on Windows and on the Linux GitHub runner.
const tree = spawnSync("git", ["ls-tree", "-r", "--name-only", `HEAD:${pluginPath}`], { encoding: "utf8" });
if (tree.status !== 0) throw new Error(tree.stderr || "Unable to list committed plugin files");
const files = tree.stdout.split(/\r?\n/).filter(Boolean).sort();
const localParts = [];
const centralParts = [];
let offset = 0;

for (const file of files) {
  const blob = spawnSync("git", ["show", `HEAD:${pluginPath}/${file}`], { encoding: null, maxBuffer: 16 * 1024 * 1024 });
  if (blob.status !== 0) throw new Error(`Unable to read committed plugin file ${file}`);
  const data = Buffer.from(blob.stdout);
  const name = Buffer.from(`popi-connector/${file.replaceAll("\\", "/")}`, "utf8");
  const checksum = crc32(data);
  const local = Buffer.concat([
    uint32(0x04034b50), uint16(20), uint16(0x0800), uint16(0), uint16(0), uint16(0x0021),
    uint32(checksum), uint32(data.length), uint32(data.length), uint16(name.length), uint16(0), name,
  ]);
  const central = Buffer.concat([
    uint32(0x02014b50), uint16(0x0314), uint16(20), uint16(0x0800), uint16(0), uint16(0), uint16(0x0021),
    uint32(checksum), uint32(data.length), uint32(data.length), uint16(name.length), uint16(0), uint16(0),
    uint16(0), uint16(0), uint32(0o100644 << 16), uint32(offset), name,
  ]);
  localParts.push(local, data);
  centralParts.push(central);
  offset += local.length + data.length;
}

const central = Buffer.concat(centralParts);
const end = Buffer.concat([
  uint32(0x06054b50), uint16(0), uint16(0), uint16(files.length), uint16(files.length),
  uint32(central.length), uint32(offset), uint16(0),
]);
await writeFile(archive, Buffer.concat([...localParts, central, end]));

const bytes = await readFile(archive);
if (bytes.length < 100 || bytes.subarray(0, 4).toString("hex") !== "504b0304") throw new Error("Generated plugin archive is invalid");
const checksum = createHash("sha256").update(bytes).digest("hex");
await writeFile(`${archive}.sha256`, `${checksum}  popi-connector.zip\n`, "utf8");
console.log(`Created POPI Connector ${headerVersion} at dist/popi-connector.zip (${bytes.length} bytes)`);
console.log(`SHA-256 ${checksum}`);
