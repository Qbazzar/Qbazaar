'use client';

/**
 * Free interactive map picker (Leaflet + OpenStreetMap tiles — no API key, no
 * subscription). The seller clicks/drags a pin; we report back {lat, lng}.
 *
 * Leaflet is loaded directly (no react-leaflet) to avoid React 19 peer-dep
 * friction. We manage the map instance manually across mount/unmount.
 */
import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Default view: Qatar. Used until the user (or a city default) sets a pin.
const QATAR_CENTER: [number, number] = [25.2854, 51.531];

// Leaflet's default marker assets break under bundlers; point at the CDN copies.
const markerIcon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
  shadowSize: [41, 41],
});

interface Props {
  value: { lat: number; lng: number } | null;
  onChange: (coords: { lat: number; lng: number }) => void;
  /** Optional starting center (e.g. the selected city) when no pin is set yet. */
  center?: { lat: number; lng: number } | null;
}

export function MapPicker({ value, onChange, center }: Props) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const mapRef = useRef<L.Map | null>(null);
  const markerRef = useRef<L.Marker | null>(null);
  // Keep the latest onChange without re-initialising the map.
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  // Initialise the map once.
  useEffect(() => {
    if (!containerRef.current || mapRef.current) return;

    const start: [number, number] = value
      ? [value.lat, value.lng]
      : center
        ? [center.lat, center.lng]
        : QATAR_CENTER;

    const map = L.map(containerRef.current, {
      center: start,
      zoom: value ? 14 : 11,
      scrollWheelZoom: false,
    });
    mapRef.current = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 19,
    }).addTo(map);

    const placeMarker = (lat: number, lng: number) => {
      if (markerRef.current) {
        markerRef.current.setLatLng([lat, lng]);
      } else {
        const marker = L.marker([lat, lng], {
          icon: markerIcon,
          draggable: true,
        }).addTo(map);
        marker.on('dragend', () => {
          const p = marker.getLatLng();
          onChangeRef.current({ lat: p.lat, lng: p.lng });
        });
        markerRef.current = marker;
      }
    };

    if (value) placeMarker(value.lat, value.lng);

    map.on('click', (e: L.LeafletMouseEvent) => {
      placeMarker(e.latlng.lat, e.latlng.lng);
      onChangeRef.current({ lat: e.latlng.lat, lng: e.latlng.lng });
    });

    // Leaflet needs a size recalc once it's visible in the layout.
    setTimeout(() => map.invalidateSize(), 0);

    return () => {
      map.remove();
      mapRef.current = null;
      markerRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Re-center when a city default arrives and the user hasn't pinned yet.
  useEffect(() => {
    if (!mapRef.current || value || !center) return;
    mapRef.current.setView([center.lat, center.lng], 11);
  }, [center, value]);

  return (
    <div
      ref={containerRef}
      className="border-ink-200 h-72 w-full overflow-hidden rounded-xl border"
      role="application"
      aria-label="map"
    />
  );
}
