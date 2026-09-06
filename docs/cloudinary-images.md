# Transfer existing attraction images

Set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY and CLOUDINARY_API_SECRET in
the local .env. Never commit credentials. Run commands from the artisan folder.

Preview a batch (no network upload):

```sh
php artisan images:cloudinary --limit=3
```

Upload a small batch:

```sh
php artisan images:cloudinary --upload --limit=3
```

After verifying the images in Cloudinary, upload further batches:

```sh
php artisan images:cloudinary --upload --limit=100
```

The command scans public/images and public/attraction_images. It preserves local
files and never connects to a database. Successful uploads are recorded under
storage/app/cloudinary/CLOUD_NAME/manifest.json. Keep this file to resume.
Content hashes provide stable Cloudinary IDs and prevent overwriting existing assets.
The default limit is five uploads; increase it deliberately after checking account usage.

After a successful batch, update-aiven-images.sql in the same directory contains
updates for attraction_image.image_path and attractions.image_path. Back up
Aiven, review the SQL, and run it in Workbench with defaultdb selected. Only
matching local relative paths are changed. Already updated URLs are left alone.
Do not reimport the full database. Confirm a sample image URL loads before applying.

HTTP 401 indicates authentication failed: check that all three settings come
from the same Cloudinary environment, the API key is active, and the computer's
clock is correct. After editing .env, run php artisan config:clear locally if
configuration has been cached.

This command handles existing files only. Admin uploads use Cloudinary in
production and whenever Cloudinary is configured locally. Configure all three
Cloudinary settings on Render. Failed uploads roll back the database changes;
assets already uploaded before a later failure may remain in Cloudinary.
Deleting a place removes its database references, not shared Cloudinary assets.
