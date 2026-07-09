const { execFile } = require("child_process");
const path = require("path");
const browserSync = require("browser-sync").create();
const chokidar = require("chokidar");

const root = path.resolve(__dirname, "..");
const sourceGlobs = ["sources/**/*.{html,css,js}"];
const publicGlobs = ["index.html", "style.css", "app.js", "assets/**/*"];
let building = false;
let queued = false;

function runBuild(reason = "startup") {
  if (building) {
    queued = true;
    return;
  }

  building = true;
  console.log("Building site (" + reason + ")...");

  execFile(process.execPath, ["tools/build.js"], { cwd: root }, (error, stdout, stderr) => {
    building = false;

    if (stdout) process.stdout.write(stdout);
    if (stderr) process.stderr.write(stderr);

    if (error) {
      console.error("Build failed:", error.message);
    } else {
      console.log("Build complete. BrowserSync will reload from generated files.");
    }

    if (queued) {
      queued = false;
      runBuild("queued changes");
    }
  });
}

function noCache(_req, res, next) {
  res.setHeader("Cache-Control", "no-store, no-cache, must-revalidate, proxy-revalidate");
  res.setHeader("Pragma", "no-cache");
  res.setHeader("Expires", "0");
  next();
}

browserSync.init({
  server: {
    baseDir: root,
    middleware: [noCache],
  },
  files: publicGlobs,
  watchOptions: {
    ignoreInitial: true,
  },
  open: false,
  notify: false,
  ghostMode: false,
});

runBuild();

chokidar
  .watch(sourceGlobs, {
    cwd: root,
    ignoreInitial: true,
    awaitWriteFinish: {
      stabilityThreshold: 180,
      pollInterval: 50,
    },
  })
  .on("all", (event, filePath) => {
    runBuild(event + " " + filePath);
  });
