import { createHash } from "node:crypto";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import { spawnSync } from "node:child_process";
import { resolve } from "node:path";

const pluginPath = "popishop-cart-handoff";
const pluginFile = `${pluginPath}/popishop-cart-handoff.php`;
const outputDir = resolve("dist");
const archive = resolve(outputDir, "popishop-cart-handoff.zip");

const dirty = spawnSync("git", ["status", "--porcelain", "--", pluginPath], { encoding: "utf8" });
if (dirty.status !== 0) throw new Error(dirty.stderr || "Unable to inspect the WordPress plugin source");
if (dirty.stdout.trim()) throw new Error("Commit the WordPress plugin changes before packaging them");

const source = await readFile(pluginFile, "utf8");
const headerVersion = source.match(/^ \* Version:\s*([^\s]+)$/m)?.[1];
const runtimeVersion = source.match(/const VERSION = '([^']+)';/)?.[1];
if (!headerVersion || headerVersion !== runtimeVersion) throw new Error("WordPress plugin header and runtime versions do not match");

await mkdir(outputDir, { recursive: true });
const packaged = spawnSync("git", ["archive", "--format=zip", "--prefix=popishop-cart-handoff/", `--output=${archive}`, `HEAD:${pluginPath}`], { stdio: "inherit" });
if (packaged.status !== 0) throw new Error("Unable to create the WordPress plugin archive");

const bytes = await readFile(archive);
if (bytes.length < 100 || bytes.subarray(0, 4).toString("hex") !== "504b0304") throw new Error("Generated plugin archive is invalid");
const checksum = createHash("sha256").update(bytes).digest("hex");
await writeFile(`${archive}.sha256`, `${checksum}  popishop-cart-handoff.zip\n`, "utf8");
console.log(`Created POPIshop Cart Handoff ${headerVersion} at dist/popishop-cart-handoff.zip (${bytes.length} bytes)`);
console.log(`SHA-256 ${checksum}`);
