const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");

const root = path.resolve(__dirname, "..");
const zipName = "torque-link-media-site.zip";
const zipPath = path.join(root, zipName);
const files = ["index.html", "style.css", "app.js", "assets"];

fs.rmSync(zipPath, { force: true });

execFileSync(process.execPath, ["tools/build.js"], {
  cwd: root,
  env: { ...process.env, NODE_ENV: "production" },
  stdio: "inherit",
});

execFileSync("zip", ["-r", zipName, ...files], {
  cwd: root,
  stdio: "inherit",
});

console.log("Created " + zipPath);
