module.exports = ({ env }) => ({
  plugins: [
    require("postcss-import"),
    require("autoprefixer"),
    ...(env === "production" ? [require("cssnano")({ preset: "default" })] : []),
  ],
});
