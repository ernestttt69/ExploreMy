document.addEventListener('DOMContentLoaded', () => {
    const options = document.querySelectorAll(
        '.preference-option'
    );

    options.forEach(option => {
        const radio = option.querySelector(
            'input[type="radio"]'
        );

        radio.addEventListener('change', () => {
            options.forEach(item => {
                item.classList.remove('selected');
            });

            option.classList.add('selected');
        });
    });
});

window.initRouteMap = () => {
    const mapElement = document.getElementById('route-map');
    const route = window.routeMapData;

    if (!mapElement || !route || !window.google) {
        return;
    }

    const map = new google.maps.Map(mapElement, {
        center: {
            lat: route.stops[0].latitude,
            lng: route.stops[0].longitude,
        },
        zoom: 14,
        mapTypeControl: false,
        streetViewControl: false,
    });
    const bounds = new google.maps.LatLngBounds();
    const colours = ['#1565c0', '#7b1fa2', '#00897b', '#ef6c00', '#c62828'];

    route.stops.forEach((stop, index) => {
        const position = {lat: stop.latitude, lng: stop.longitude};
        bounds.extend(position);

        new google.maps.Marker({
            map,
            position,
            label: String(index + 1),
            title: stop.name,
        });
    });

    route.transit_legs.forEach((leg, index) => {
        leg.encoded_polylines.forEach(encodedPolyline => {
            const path = google.maps.geometry.encoding.decodePath(
                encodedPolyline
            );

            path.forEach(point => bounds.extend(point));

            new google.maps.Polyline({
                map,
                path,
                strokeColor: colours[index % colours.length],
                strokeOpacity: 0.9,
                strokeWeight: 5,
            });
        });
    });

    map.fitBounds(bounds, 40);
};
