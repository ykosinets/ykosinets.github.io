import fs from "node:fs";
import path from "node:path";
import { execFileSync } from "node:child_process";

const projectRoot = process.cwd();
const gitRoot = execFileSync("git", ["rev-parse", "--show-toplevel"], {
  cwd: projectRoot,
  encoding: "utf8"
}).trim();

const srcDir = path.join(projectRoot, "src");
const publicDir = path.join(projectRoot, "public");
const outputDir = gitRoot;

function read(file) {
  return fs.readFileSync(file, "utf8");
}

function includePartials(input, fromFile) {
  return input.replace(/<!--\s*@include\s+([^ ]+)\s*-->/g, (_, includePath) => {
    const resolved = path.join(srcDir, "components", includePath);
    if (!fs.existsSync(resolved)) {
      throw new Error(`Missing include "${includePath}" referenced from ${fromFile}`);
    }
    return includePartials(read(resolved), resolved);
  });
}

function copyDir(source, target) {
  if (!fs.existsSync(source)) return;
  fs.mkdirSync(target, { recursive: true });
  for (const entry of fs.readdirSync(source, { withFileTypes: true })) {
    const sourcePath = path.join(source, entry.name);
    const targetPath = path.join(target, entry.name);
    if (entry.isDirectory()) {
      copyDir(sourcePath, targetPath);
    } else {
      fs.copyFileSync(sourcePath, targetPath);
    }
  }
}

function build() {
  fs.mkdirSync(path.join(outputDir, "styles"), { recursive: true });
  copyDir(publicDir, outputDir);

  const pageFile = path.join(srcDir, "pages", "index.html");
  const html = includePartials(read(pageFile), pageFile);
  fs.writeFileSync(path.join(outputDir, "index.html"), html);

  fs.copyFileSync(
    path.join(srcDir, "styles", "main.pcss"),
    path.join(outputDir, "styles", "drifter.css")
  );

  console.log(`Built ${path.relative(projectRoot, path.join(outputDir, "index.html")) || "index.html"}`);
}

build();

if (process.argv.includes("--watch")) {
  console.log("Watching src/ and public/ for changes...");
  fs.watch(srcDir, { recursive: true }, build);
  fs.watch(publicDir, { recursive: true }, build);
}
