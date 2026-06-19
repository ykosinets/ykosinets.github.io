import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const srcDir = path.join(root, "src");
const distDir = path.join(root, "dist");
const publicDir = path.join(root, "public");

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
  fs.rmSync(distDir, { recursive: true, force: true });
  fs.mkdirSync(path.join(distDir, "styles"), { recursive: true });

  copyDir(publicDir, distDir);

  const pageFile = path.join(srcDir, "pages", "index.html");
  const html = includePartials(read(pageFile), pageFile);
  fs.writeFileSync(path.join(distDir, "index.html"), html);

  fs.copyFileSync(
    path.join(srcDir, "styles", "main.pcss"),
    path.join(distDir, "styles", "drifter.css")
  );

  console.log("Built dist/index.html");
}

build();

if (process.argv.includes("--watch")) {
  console.log("Watching src/ and public/ for changes...");
  fs.watch(srcDir, { recursive: true }, build);
  fs.watch(publicDir, { recursive: true }, build);
}
