const { spawn } = require("child_process");
const path = require("path");
const browserSync = require("browser-sync").create();

const root = path.resolve(__dirname, "..");
const decapBin = path.join(root, "node_modules/.bin/decap-server");

const decapServer = spawn(decapBin, [], {
  cwd: root,
  stdio: "inherit",
});

browserSync.init({
  server: {
    baseDir: root,
  },
  startPath: "/decap/",
  files: ["decap/**/*", "assets/**/*"],
  open: false,
  notify: false,
  ghostMode: false,
});

function shutdown() {
  decapServer.kill();
  browserSync.exit();
}

process.on("SIGINT", shutdown);
process.on("SIGTERM", shutdown);
