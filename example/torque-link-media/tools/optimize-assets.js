const fs = require("fs/promises");
const path = require("path");
const sharp = require("sharp");

const root = path.resolve(__dirname, "..");
const assetsDir = path.join(root, "assets");
const outputDir = path.join(assetsDir, "optimized");
const imageExtensions = new Set([".jpg", ".jpeg", ".png"]);
const maxDimension = 1800;
const webpQuality = 78;
const jpegQuality = 82;
const pngQuality = 82;

async function getImageFiles(dir) {
    const entries = await fs.readdir(dir, { withFileTypes: true });

    return entries
        .filter((entry) => entry.isFile())
        .map((entry) => entry.name)
        .filter((name) => imageExtensions.has(path.extname(name).toLowerCase()));
}

function optimizedPath(sourcePath, suffix) {
    const extension = path.extname(sourcePath);
    const basename = path.basename(sourcePath, extension);

    return path.join(outputDir, basename + suffix);
}

async function optimizeImage(fileName) {
    const sourcePath = path.join(assetsDir, fileName);
    const extension = path.extname(fileName).toLowerCase();
    const image = sharp(sourcePath, { animated: true }).rotate();
    const metadata = await image.metadata();
    const resize =
        metadata.width > maxDimension || metadata.height > maxDimension
            ? { width: maxDimension, height: maxDimension, fit: "inside", withoutEnlargement: true }
            : {};

    const pipeline = sharp(sourcePath, { animated: true }).rotate().resize(resize);
    const webpPath = optimizedPath(sourcePath, ".webp");
    const originalFormatPath = optimizedPath(sourcePath, extension);
    const tempOriginalFormatPath = optimizedPath(sourcePath, ".tmp" + extension);

    await pipeline.clone().webp({ quality: webpQuality, effort: 5 }).toFile(webpPath);

    if (extension === ".png") {
        await pipeline
            .clone()
            .png({ quality: pngQuality, compressionLevel: 9, palette: false })
            .toFile(tempOriginalFormatPath);
    } else {
        await pipeline.clone().jpeg({ quality: jpegQuality, mozjpeg: true }).toFile(tempOriginalFormatPath);
    }

    const sourceStat = await fs.stat(sourcePath);
    const webpStat = await fs.stat(webpPath);
    const tempOptimizedStat = await fs.stat(tempOriginalFormatPath);
    let optimizedStat = null;

    if (tempOptimizedStat.size < sourceStat.size) {
        await fs.rename(tempOriginalFormatPath, originalFormatPath);
        optimizedStat = tempOptimizedStat;
    } else {
        await fs.rm(tempOriginalFormatPath, { force: true });
        await fs.rm(originalFormatPath, { force: true });
    }

    return {
        source: path.relative(root, sourcePath),
        webp: path.relative(root, webpPath),
        optimized: optimizedStat ? path.relative(root, originalFormatPath) : null,
        sourceBytes: sourceStat.size,
        webpBytes: webpStat.size,
        optimizedBytes: optimizedStat?.size || 0,
    };
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";

    return (bytes / 1024 / 1024).toFixed(2) + " MB";
}

async function main() {
    await fs.rm(outputDir, { recursive: true, force: true });
    await fs.mkdir(outputDir, { recursive: true });

    const files = await getImageFiles(assetsDir);

    if (!files.length) {
        console.log("No JPG or PNG assets found.");
        return;
    }

    const results = await Promise.all(files.map(optimizeImage));

    console.log("Optimized image assets:");
    results.forEach((result) => {
        console.log(
            "- " +
                result.source +
                " -> " +
                result.webp +
                " (" +
                formatBytes(result.sourceBytes) +
                " to " +
                formatBytes(result.webpBytes) +
                ")"
        );
    });

    const totalSource = results.reduce((total, result) => total + result.sourceBytes, 0);
    const totalWebp = results.reduce((total, result) => total + result.webpBytes, 0);
    const totalOptimized = results.reduce((total, result) => total + result.optimizedBytes, 0);

    console.log("Original total: " + formatBytes(totalSource));
    console.log("WebP total: " + formatBytes(totalWebp));
    if (totalOptimized > 0) {
        console.log("Smaller same-format total: " + formatBytes(totalOptimized));
    }
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
