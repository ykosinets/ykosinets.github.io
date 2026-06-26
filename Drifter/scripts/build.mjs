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

function findByExt(dir, ext) {
  if (!fs.existsSync(dir)) return [];
  const files = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) files.push(...findByExt(fullPath, ext));
    if (entry.isFile() && entry.name.endsWith(ext)) files.push(fullPath);
  }
  return files.sort((a, b) => a.localeCompare(b));
}

function buildCss() {
  const globalCss = read(path.join(srcDir, "styles", "main.pcss")).trim();
  const componentCss = findByExt(path.join(srcDir, "components"), ".pcss")
    .map((file) => `/* ${path.relative(srcDir, file)} */\n${read(file).trim()}\n`)
    .join("\n");
  return `${globalCss}\n\n${componentCss}`;
}

function buildJs() {
  const componentJs = findByExt(path.join(srcDir, "components"), ".js");
  const bodies = componentJs.map((file) => {
    const body = read(file)
      .split("\n")
      .filter((line) => !line.trim().startsWith("import "))
      .join("\n")
      .trim();
    return `// ${path.relative(srcDir, file)}\n${body}`;
  }).join("\n\n");

  return `import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';\n\nconst ready = (fn) => {\n  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);\n  else fn();\n};\n\nready(() => {\n${bodies.split("\n").map((line) => `  ${line}`).join("\n")}\n});\n`;
}

function build() {
  fs.mkdirSync(path.join(outputDir, "styles"), { recursive: true });
  copyDir(publicDir, outputDir);

  const pageFile = path.join(srcDir, "pages", "index.html");
  const html = includePartials(read(pageFile), pageFile);
  fs.writeFileSync(path.join(outputDir, "index.html"), html);

  fs.writeFileSync(path.join(outputDir, "styles", "drifter.css"), buildCss());
  fs.mkdirSync(path.join(outputDir, "assets"), { recursive: true });
  fs.writeFileSync(path.join(outputDir, "assets", "site.js"), buildJs());

  console.log(`Built ${path.relative(projectRoot, path.join(outputDir, "index.html")) || "index.html"}`);
}

build();

if (process.argv.includes("--watch")) {
  console.log("Watching src/ and public/ for changes...");
  fs.watch(srcDir, { recursive: true }, build);
  fs.watch(publicDir, { recursive: true }, build);
}
