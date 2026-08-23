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
OUTPUT_DIR = os.path.join(BASE_DIR, "output")

ATTRACTION_CSV = os.path.join(
    OUTPUT_DIR,
    "attractions.csv"
)

IMAGE_SOURCE_CSV = os.path.join(
    OUTPUT_DIR,
    "attraction_image.csv"
)

ATTRACTION_PREFERENCE_CSV = os.path.join(
    OUTPUT_DIR,
    "attraction_preferences.csv"
)

os.makedirs(OUTPUT_DIR, exist_ok=True)

MIN_ATTRACTIONS = 2100
MAX_ATTRACTIONS = 2300

STATES = {
    1: "Johor",
    2: "Kedah",
    3: "Kelantan",
    4: "Melaka",
    5: "Negeri Sembilan",
    6: "Pahang",
    7: "Penang",
    8: "Perak",
    9: "Perlis",
    10: "Sabah",
    11: "Sarawak",
    12: "Selangor",
    13: "Terengganu",
    14: "Kuala Lumpur",
    15: "Putrajaya",
    16: "Labuan"
}

CATEGORIES = {
    "Nature": 1,
    "Adventure": 2,
    "Culture & Heritage": 3,
    "Family": 4,
    "Food & Drinks": 5,
    "Shopping": 6,
    "Beach": 7,
    "City": 8,
    "Relaxation": 9
}

SEARCH_TERMS = [
    "tourist attractions",
    "tourist places",
    "places to visit",
    "things to do",
    "nature attractions",
    "adventure attractions",
    "cultural attractions",
    "heritage attractions",
    "family attractions",
    "beaches",
    "parks",
    "museums",
    "shopping attractions",
    "food attractions",
    "waterfalls",
    "theme parks",
    "recreation parks"
]


def detect_categories(types, name, description):
    text = " ".join([
        str(types or ""),
        str(name or ""),
        str(description or "")
    ]).lower()

    categories = []

    if any(word in text for word in [
        "park",
        "forest",
        "waterfall",
        "garden",
        "nature",
        "lake",
        "cave",
        "mountain",
        "hill",
        "island",
        "wildlife",
        "zoo",
        "botanical"
    ]):
        categories.append("Nature")

    if any(word in text for word in [
        "adventure",
        "rafting",
        "zipline",
        "climbing",
        "hiking",
        "trekking",
        "extreme",
        "outdoor",
        "water sport",
        "watersport",
        "paintball",
        "escape"
    ]):
        categories.append("Adventure")

    if any(word in text for word in [
        "museum",
        "heritage",
        "historical",
        "history",
        "culture",
        "cultural",
        "temple",
        "mosque",
        "church",
        "fort",
        "palace",
        "memorial",
        "village",
        "traditional",
        "gallery"
    ]):
        categories.append("Culture & Heritage")

    if any(word in text for word in [
        "family",
        "kids",
        "children",
        "theme park",
        "water park",
        "zoo",
        "aquarium",
        "playground",
        "indoor playground"
    ]):
        categories.append("Family")

    if any(word in text for word in [
        "restaurant",
        "cafe",
        "coffee",
        "food",
        "dining",
        "eatery",
        "street food",
        "food court"
    ]):
        categories.append("Food & Drinks")

    if any(word in text for word in [
        "mall",
        "shopping",
        "market",
        "bazaar",
        "outlet",
        "supermarket",
        "complex"
    ]):
        categories.append("Shopping")

    if any(word in text for word in [
        "beach",
        "island",
        "coast",
        "seaside",
        "marine",
        "snorkeling",
        "diving"
    ]):
        categories.append("Beach")

    if any(word in text for word in [
        "city",
        "city centre",
        "city center",
        "urban",
        "downtown",
        "square",
        "tower",
        "street"
    ]):
        categories.append("City")

    if any(word in text for word in [
        "spa",
        "resort",
        "relax",
        "relaxation",
        "hot spring",
        "onsen",
        "wellness",
        "retreat"
    ]):
        categories.append("Relaxation")

    if not categories:
        categories.append("City")

    return list(dict.fromkeys(categories))


def get_state_id(address):
    address = str(address or "").lower()

    state_aliases = {
        "Johor": ["johor"],
        "Kedah": ["kedah"],
        "Kelantan": ["kelantan"],
        "Melaka": ["melaka", "malacca"],
        "Negeri Sembilan": ["negeri sembilan"],
        "Pahang": ["pahang"],
        "Penang": ["penang", "pulau pinang"],
        "Perak": ["perak"],
        "Perlis": ["perlis"],
        "Sabah": ["sabah"],
        "Sarawak": ["sarawak"],
        "Selangor": ["selangor"],
        "Terengganu": ["terengganu"],
        "Kuala Lumpur": ["kuala lumpur"],
        "Putrajaya": ["putrajaya"],
        "Labuan": ["labuan"]
    }

    for state_id, state_name in STATES.items():
        for alias in state_aliases.get(state_name, []):
            if alias in address:
                return state_id

    return None


def format_opening_hours(place):
    hours = place.get(
        "regularOpeningHours",
        {}
    )

    descriptions = hours.get(
        "weekdayDescriptions",
        []
    )

    return "\n".join(descriptions)


def get_budget_level(price_level):
    mapping = {
        "PRICE_LEVEL_FREE": "Free",
        "PRICE_LEVEL_INEXPENSIVE": "Low",
        "PRICE_LEVEL_MODERATE": "Medium",
        "PRICE_LEVEL_EXPENSIVE": "High",
        "PRICE_LEVEL_VERY_EXPENSIVE": "Very High"
    }

    return mapping.get(
        price_level,
        "Price unavailable"
    )


def detect_transport_from_text(text):
    text = str(text or "").lower()

    transport = []

    if any(word in text for word in [
        "ktm",
        "komuter",
        "keretapi tanah melayu",
        "malayan railway"
    ]):
        transport.append("KTM")

    if any(word in text for word in [
        "mrt",
        "mass rapid transit"
    ]):
        transport.append("MRT")

    if any(word in text for word in [
        "lrt",
        "light rail transit"
    ]):
        transport.append("LRT")

    if "monorail" in text:
        transport.append("Monorail")

    if any(word in text for word in [
        "bus",
        "rapid kl",
        "rapid penang",
        "rapid bus",
        "mybas"
    ]):
        transport.append("Bus")

    return list(dict.fromkeys(transport))


def get_nearby_transport(place):
    text_parts = []

    display_name = place.get(
        "displayName",
        {}
    ).get(
        "text",
        ""
    )

    address = place.get(
        "formattedAddress",
        ""
    )

    description = place.get(
        "editorialSummary",
        {}
    ).get(
        "text",
        ""
    )

    text_parts.append(display_name)
    text_parts.append(address)
    text_parts.append(description)

    text = " ".join(text_parts)

    return detect_transport_from_text(text)


def get_image_rows(place):
    rows = []

    photos = place.get(
        "photos",
        []
    )

    attraction_name = place.get(
        "displayName",
        {}
    ).get(
        "text",
        ""
    )

    place_id = place.get(
        "id",
        ""
    )

    for photo in photos[:3]:
        photo_name = photo.get(
            "name",
            ""
        )

        if photo_name:
            rows.append({
                "place_id": place_id,
                "attraction_name": attraction_name,
                "image_path": photo_name
            })

    return rows


def search_places(query):
    url = (
        "https://places.googleapis.com/v1/places:searchText"
    )

    headers = {
        "X-Goog-Api-Key": API_KEY,
        "X-Goog-FieldMask": ",".join([
            "places.id",
            "places.displayName",
            "places.formattedAddress",
            "places.types",
            "places.editorialSummary",
            "places.regularOpeningHours",
            "places.priceLevel",
            "places.rating",
            "places.photos"
        ])
    }

    payload = {
        "textQuery": query,
        "languageCode": "en",
        "regionCode": "MY",
        "pageSize": 20
    }

    try:
        response = requests.post(
            url,
            headers=headers,
            json=payload,
            timeout=(10, 60)
        )

        if response.status_code != 200:
            print(
                f"Search failed: "
                f"{response.status_code}"
            )
            return []

        return response.json().get(
            "places",
            []
        )

    except requests.RequestException as error:
        print(
            f"Search error: {error}"
        )
        return []


print("=" * 70)
print("ExploreMY Google Places Scraper")
print("=" * 70)

print(
    f"Target: {MIN_ATTRACTIONS} - "
    f"{MAX_ATTRACTIONS} attractions"
)

attractions = []
image_rows = []
attraction_preference_rows = []

seen_place_ids = set()

for state_id, state_name in STATES.items():

    if len(attractions) >= MAX_ATTRACTIONS:
        break

    print()
    print(
        f"Searching {state_name}..."
    )

    for search_term in SEARCH_TERMS:

        if len(attractions) >= MAX_ATTRACTIONS:
            break

        query = (
            f"{search_term} in "
            f"{state_name}, Malaysia"
        )

        places = search_places(query)

        for place in places:

            if len(attractions) >= MAX_ATTRACTIONS:
                break

            place_id = place.get(
                "id",
                ""
            )

            if not place_id:
                continue

            if place_id in seen_place_ids:
                continue

            seen_place_ids.add(
                place_id
            )

            attraction_name = place.get(
                "displayName",
                {}
            ).get(
                "text",
                ""
            ).strip()

            address = place.get(
                "formattedAddress",
                ""
            ).strip()

            description = place.get(
                "editorialSummary",
                {}
            ).get(
                "text",
                ""
            )

            types = place.get(
                "types",
                []
            )

            categories = detect_categories(
                types,
                attraction_name,
                description
            )

            detected_state_id = get_state_id(
                address
            )

            if detected_state_id is None:
                detected_state_id = state_id

            price_level = get_budget_level(
                place.get(
                    "priceLevel"
                )
            )

            nearby_transport = get_nearby_transport(
                place
            )

            attraction = {
                "place_id": place_id,
                "state_id": detected_state_id,
                "attraction_name": attraction_name,
                "category": ", ".join(categories),
                "description": description,
                "location": address,
                "operating_hours": format_opening_hours(
                    place
                ),
                "entrance_fee": price_level,
                "budget_level": price_level,
                "nearby_transport": ", ".join(
                    nearby_transport
                ),
                "rating": place.get(
                    "rating"
                )
            }

            attractions.append(
                attraction
            )

            for category in categories:

                preference_id = CATEGORIES.get(
                    category
                )

                if preference_id:

                    attraction_preference_rows.append({
                        "place_id": place_id,
                        "preference_id": preference_id
                    })

            image_rows.extend(
                get_image_rows(place)
            )

            transport_text = (
                ", ".join(nearby_transport)
                if nearby_transport
                else "None detected"
            )

            print(
                f"[{len(attractions)}] "
                f"{attraction_name} | "
                f"{', '.join(categories)} | "
                f"{transport_text}"
            )

        time.sleep(0.2)


print()
print("=" * 70)
print("Removing duplicates...")
print("=" * 70)

attraction_df = pd.DataFrame(
    attractions
)

if not attraction_df.empty:
    attraction_df = attraction_df.drop_duplicates(
        subset=["place_id"],
        keep="first"
    )

image_df = pd.DataFrame(
    image_rows
)

if not image_df.empty:
    image_df = image_df.drop_duplicates(
        subset=[
            "place_id",
            "image_path"
        ],
        keep="first"
    )

attraction_preference_df = pd.DataFrame(
    attraction_preference_rows
)

if not attraction_preference_df.empty:
    attraction_preference_df = (
        attraction_preference_df.drop_duplicates(
            subset=[
                "place_id",
                "preference_id"
            ],
            keep="first"
        )
    )


attraction_df.to_csv(
    ATTRACTION_CSV,
    index=False,
    encoding="utf-8-sig"
)

image_df.to_csv(
    IMAGE_SOURCE_CSV,
    index=False,
    encoding="utf-8-sig"
)

attraction_preference_df.to_csv(
    ATTRACTION_PREFERENCE_CSV,
    index=False,
    encoding="utf-8-sig"
)


transport_count = 0

if not attraction_df.empty:

    transport_count = (
        attraction_df[
            "nearby_transport"
        ]
        .fillna("")
        .astype(str)
        .str.strip()
        .ne("")
        .sum()
    )

without_transport = (
    len(attraction_df) - transport_count
)


print()
print("=" * 70)
print("SCRAPING FINISHED")
print("=" * 70)

print(
    "Attractions:",
    len(attraction_df)
)

print(
    "Image records:",
    len(image_df)
)

print(
    "Attraction-category records:",
    len(attraction_preference_df)
)

print(
    "Attractions with nearby transport:",
    transport_count
)

print(
    "Attractions without nearby transport:",
    without_transport
)

print()
print(
    "Attraction CSV:",
    ATTRACTION_CSV
)

print(
    "Image CSV:",
    IMAGE_SOURCE_CSV
)

print(
    "Attraction preference CSV:",
    ATTRACTION_PREFERENCE_CSV
)