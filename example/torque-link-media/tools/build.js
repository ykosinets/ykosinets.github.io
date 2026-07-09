const fs = require("fs");
const path = require("path");
const postcss = require("postcss");
const postcssImport = require("postcss-import");
const autoprefixer = require("autoprefixer");
const cssnano = require("cssnano");

const root = path.resolve(__dirname, "..");
const sourceRoot = path.join(root, "sources");
const isProduction = process.env.NODE_ENV === "production";

function readSource(relativePath) {
  return fs.readFileSync(path.join(sourceRoot, relativePath), "utf8");
}

function writePublic(relativePath, content) {
  const target = path.join(root, relativePath);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, content);
}

function resolveHtmlIncludes(content, fromDir = sourceRoot) {
  return content.replace(/<!--\s*@include\s+([^\s]+)\s*-->/g, (_, includePath) => {
    const absolutePath = path.resolve(fromDir, includePath);
    return resolveHtmlIncludes(fs.readFileSync(absolutePath, "utf8"), path.dirname(absolutePath));
  });
}

function resolveJsIncludes(content, fromDir = sourceRoot) {
  return content.replace(/^\s*\/\/\s*@include\s+(.+)$/gm, (_, includePath) => {
    const absolutePath = path.resolve(fromDir, includePath.trim());
    return resolveJsIncludes(fs.readFileSync(absolutePath, "utf8"), path.dirname(absolutePath));
  });
}

async function build() {
  writePublic("index.html", resolveHtmlIncludes(readSource("index.html")));
  writePublic("app.js", resolveJsIncludes(readSource("app.js")));

  const css = await postcss([
    postcssImport(),
    autoprefixer(),
    ...(isProduction ? [cssnano({ preset: "default" })] : []),
  ]).process(readSource("style.css"), {
    from: path.join(sourceRoot, "style.css"),
    to: path.join(root, "style.css"),
  });

  writePublic("style.css", css.css);
}

build().catch((error) => {
  console.error(error);
  process.exit(1);
});
