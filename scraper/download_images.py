import os
import time
import requests
import pandas as pd
from dotenv import load_dotenv

load_dotenv()

API_KEY = os.getenv("GOOGLE_PLACES_API_KEY")

if not API_KEY:
    raise ValueError(
        "GOOGLE_PLACES_API_KEY was not found in the .env file."
    )

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

OUTPUT_DIR = os.path.join(
    BASE_DIR,
    "output"
)

IMAGE_DIR = os.path.join(
    OUTPUT_DIR,
    "images"
)

SOURCE_CSV = os.path.join(
    OUTPUT_DIR,
    "attraction_image.csv"
)

IMAGE_CSV = os.path.join(
    OUTPUT_DIR,
    "downloaded_images.csv"
)

os.makedirs(
    IMAGE_DIR,
    exist_ok=True
)

if not os.path.exists(SOURCE_CSV):
    raise FileNotFoundError(
        f"Cannot find: {SOURCE_CSV}"
    )

df = pd.read_csv(
    SOURCE_CSV
)

print("=" * 70)
print("ExploreMY Image Downloader")
print("=" * 70)

print(
    f"Images in CSV: {len(df)}"
)

print(
    f"Image folder: {IMAGE_DIR}"
)

print()

downloaded = []

download_count = 0
skipped_count = 0
failed_count = 0

name_count = {}

for index, row in df.iterrows():

    attraction_name = str(
        row["attraction_name"]
    ).strip()

    image_path = str(
        row["image_path"]
    ).strip()

    if not attraction_name or not image_path:
        continue

    safe_name = "".join(
        char
        if char.isalnum() or char in " _-"
        else "_"
        for char in attraction_name
    )

    safe_name = "_".join(
        safe_name.split()
    )

    name_count[safe_name] = (
        name_count.get(
            safe_name,
            0
        ) + 1
    )

    image_number = name_count[
        safe_name
    ]

    filename = (
        f"{safe_name}_{image_number}.jpg"
    )

    save_path = os.path.join(
        IMAGE_DIR,
        filename
    )

    if os.path.exists(save_path):

        print(
            f"[SKIP] {filename}"
        )

        downloaded.append({
            "place_id": row["place_id"],
            "attraction_name": attraction_name,
            "image_path": f"images/{filename}"
        })

        skipped_count += 1

        continue

    url = (
        "https://places.googleapis.com/v1/"
        f"{image_path}/media"
        "?maxWidthPx=800"
        "&maxHeightPx=800"
        f"&key={API_KEY}"
    )

    success = False

    for attempt in range(3):

        try:

            print(
                f"[DOWNLOAD] "
                f"{index + 1}/{len(df)} "
                f"{filename}"
            )

            response = requests.get(
                url,
                timeout=(10, 120)
            )

            if response.status_code == 200:

                with open(
                    save_path,
                    "wb"
                ) as file:
                    file.write(
                        response.content
                    )

                downloaded.append({
                    "place_id": row["place_id"],
                    "attraction_name": attraction_name,
                    "image_path": f"images/{filename}"
                })

                download_count += 1
                success = True

                break

            print(
                f"HTTP Error: {response.status_code}"
            )

        except requests.RequestException as error:

            print(
                f"Attempt {attempt + 1}/3 failed:"
            )

            print(error)

        if attempt < 2:
            time.sleep(2)

    if not success:

        print(
            f"[FAILED] {filename}"
        )

        failed_count += 1

pd.DataFrame(
    downloaded
).to_csv(
    IMAGE_CSV,
    index=False,
    encoding="utf-8-sig"
)

print()
print("=" * 70)
print("IMAGE DOWNLOAD FINISHED")
print("=" * 70)

print(
    "Images in CSV:",
    len(df)
)

print(
    "Newly downloaded:",
    download_count
)

print(
    "Already existed:",
    skipped_count
)

print(
    "Failed:",
    failed_count
)

print(
    "Total available:",
    len(downloaded)
)

print()
print(
    "Saved images to:",
    IMAGE_DIR
)

print(
    "Image mapping:",
    IMAGE_CSV
)