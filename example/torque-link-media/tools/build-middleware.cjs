const { execFileSync } = require("child_process");

module.exports = function buildMiddleware(_req, _res, next) {
  try {
    execFileSync("node", ["tools/build.js"], { stdio: "inherit" });
  } catch (error) {
    console.error(error);
  }

  next();
};
