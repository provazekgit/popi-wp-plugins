import { createHash } from "node:crypto";
import { mkdtemp, mkdir, readFile, rm, writeFile } from "node:fs/promises";
import { spawnSync } from "node:child_process";
import { basename, join, resolve } from "node:path";
import { tmpdir } from "node:os";

const sources = [
  {
    source: "popi-migration-recovery-guard/popi-migration-recovery-guard.php",
    target: "mu-plugins/popi-migration-recovery-guard.php",
  },
  {
    source: "popishop-staging-guard/popishop-staging-guard.php",
    target: "mu-plugins/popishop-staging-guard.php",
  },
  { source: "migration-toolkit/README.md", target: "README.md" },
  {
    source: "migration-toolkit/wp-config.migration.example.php",
    target: "wp-config.migration.example.php",
  },
];

const trackedPaths = [...new Set(sources.map(({ source }) => source).concat(import.meta.url.endsWith("package-migration-toolkit.mjs") ? ["scripts/package-migration-toolkit.mjs"] : []))];
const dirty = spawnSync("git", ["status", "--porcelain", "--", ...trackedPaths], { encoding: "utf8" });
if (dirty.status !== 0) throw new Error(dirty.stderr || "Unable to inspect migration toolkit sources");
if (dirty.stdout.trim()) throw new Error("Commit the migration toolkit sources before packaging them");

const commit = spawnSync("git", ["rev-parse", "HEAD"], { encoding: "utf8" });
if (commit.status !== 0) throw new Error(commit.stderr || "Unable to read the source commit");

const outputDir = resolve("dist");
const archive = resolve(outputDir, "popi-wordpress-migration-toolkit.zip");
const stagingRoot = await mkdtemp(join(tmpdir(), "popi-migration-toolkit-"));
const packageRoot = join(stagingRoot, "popi-wordpress-migration-toolkit");
const manifestFiles = [];

try {
  for (const { source, target } of sources) {
    const bytes = await readFile(resolve(source));
    const destination = join(packageRoot, target);
    await mkdir(resolve(destination, ".."), { recursive: true });
    await writeFile(destination, bytes);
    manifestFiles.push({
      path: target.replaceAll("\\", "/"),
      bytes: bytes.length,
      sha256: createHash("sha256").update(bytes).digest("hex"),
    });
  }

  const manifest = {
    schemaVersion: 1,
    sourceCommit: commit.stdout.trim(),
    files: manifestFiles,
  };
  await writeFile(join(packageRoot, "manifest.json"), `${JSON.stringify(manifest, null, 2)}\n`, "utf8");
  await mkdir(outputDir, { recursive: true });

  let packaged;
  if (process.platform === "win32") {
    packaged = spawnSync(
      "tar.exe",
      ["-a", "-c", "-f", archive, "-C", stagingRoot, basename(packageRoot)],
      { stdio: "inherit" },
    );
  } else {
    packaged = spawnSync("zip", ["-qr", archive, basename(packageRoot)], { cwd: stagingRoot, stdio: "inherit" });
  }
  if (packaged.status !== 0) throw new Error("Unable to create the migration toolkit archive");

  const archiveBytes = await readFile(archive);
  if (archiveBytes.length < 100 || archiveBytes.subarray(0, 4).toString("hex") !== "504b0304") {
    throw new Error("Generated migration toolkit archive is invalid");
  }
  const checksum = createHash("sha256").update(archiveBytes).digest("hex");
  await writeFile(`${archive}.sha256`, `${checksum}  ${basename(archive)}\n`, "utf8");
  console.log(`Created dist/${basename(archive)} (${archiveBytes.length} bytes)`);
  console.log(`SHA-256 ${checksum}`);
} finally {
  await rm(stagingRoot, { recursive: true, force: true });
}
