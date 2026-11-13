import { readdir, mkdir, stat } from "node:fs/promises";
import { dirname, extname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const scriptDirectory = dirname(fileURLToPath(import.meta.url));
const imagesDirectory = resolve(scriptDirectory, "..", "..", "app", "public_root", "imgs");
const optimizedDirectory = resolve(
  scriptDirectory,
  "..",
  "..",
  "app",
  "public_root",
  "imgs_optimizadas",
);

await mkdir(optimizedDirectory, { recursive: true });

const entries = await readdir(imagesDirectory, { withFileTypes: true });
const imageFiles = entries
  .filter((entry) => entry.isFile() && extname(entry.name).toLowerCase() === ".jpg")
  .map((entry) => entry.name)
  .sort();

let totalOriginalBytes = 0;
let totalOptimizedBytes = 0;

for (const imageFile of imageFiles) {
  const sourcePath = join(imagesDirectory, imageFile);
  const destinationPath = join(optimizedDirectory, imageFile);
  const originalBytes = (await stat(sourcePath)).size;

  await sharp(sourcePath)
    .resize({ width: 900, withoutEnlargement: true })
    .jpeg({ quality: 78, mozjpeg: true })
    .toFile(destinationPath);

  const optimizedBytes = (await stat(destinationPath)).size;
  totalOriginalBytes += originalBytes;
  totalOptimizedBytes += optimizedBytes;

  console.log(
    `${imageFile}: antes ${(originalBytes / 1024).toFixed(2)} KB, después ${(optimizedBytes / 1024).toFixed(2)} KB`,
  );
}

const totalSavedBytes = totalOriginalBytes - totalOptimizedBytes;
console.log(`Total ahorrado: ${(totalSavedBytes / 1024).toFixed(2)} KB`);
