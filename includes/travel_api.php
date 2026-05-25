<?php
/**
 * Tripistry Travel API client & Aviation Route Simulator
 * Provides real travel dataset integration with local fallbacks.
 */

class TravelAPI {
    private $apiKey;
    private $localFallbackData;

    public function __construct() {
        // Retrieve API key from config if set, otherwise try to load from constant/session
        global $opentripmap_api_key;
        $this->apiKey = isset($opentripmap_api_key) ? trim($opentripmap_api_key) : '';
        
        // Build rich localized fail-safe datasets for major hubs
        $this->initializeFallbackData();
    }

    /**
     * Resolve a city's coordinates (latitude and longitude)
     */
    public function getCoordinates($city) {
        $cityClean = strtolower(trim($city));
        
        // Check local fallbacks first for instant high-quality response
        if (isset($this->localFallbackData[$cityClean])) {
            $fallback = $this->localFallbackData[$cityClean];
            return [
                'name' => $fallback['name'],
                'country' => $fallback['country'],
                'lat' => $fallback['lat'],
                'lon' => $fallback['lon'],
                'source' => 'local_fallback'
            ];
        }

        // If API key is available, attempt real-time geocoding
        if (!empty($this->apiKey)) {
            $url = "https://api.opentripmap.com/0.1/en/places/geoname?name=" . urlencode($city) . "&apikey=" . $this->apiKey;
            $response = $this->fetchUrl($url);
            if ($response && isset($response['lat']) && isset($response['lon'])) {
                return [
                    'name' => $response['name'] ?? $city,
                    'country' => $response['country'] ?? '',
                    'lat' => (float)$response['lat'],
                    'lon' => (float)$response['lon'],
                    'source' => 'opentripmap_api'
                ];
            }
        }

        // Broad matching fallback for unspecified cities: default to Cape Town
        return [
            'name' => 'Cape Town',
            'country' => 'ZA',
            'lat' => -33.9249,
            'lon' => 18.4241,
            'source' => 'default_fallback'
        ];
    }

    /**
     * Query places within a given radius around coordinates
     */
    public function getPlacesByRadius($lat, $lon, $kinds, $radius = 10000, $limit = 12) {
        $lat = (float)$lat;
        $lon = (float)$lon;
        
        // Find nearest fallback city to see if we can use premium local cache
        $nearestCity = $this->findNearestFallbackCity($lat, $lon);
        if ($nearestCity !== null) {
            $cityData = $this->localFallbackData[$nearestCity];
            $results = [];
            
            // Filter by kinds: lodging, culinary, sights
            if (strpos($kinds, 'accommodation') !== false) {
                $results = $cityData['accommodation'];
            } elseif (strpos($kinds, 'catering') !== false) {
                $results = $cityData['restaurants'];
            } else {
                $results = $cityData['attractions'];
            }
            
            // Slice to limit and return
            return array_slice($results, 0, $limit);
        }

        // If external API key is active, fetch from OpenTripMap
        if (!empty($this->apiKey)) {
            $url = "https://api.opentripmap.com/0.1/en/places/radius?radius={$radius}&lon={$lon}&lat={$lat}&kinds=" . urlencode($kinds) . "&limit={$limit}&apikey=" . $this->apiKey;
            $response = $this->fetchUrl($url);
            
            if (is_array($response) && !empty($response['features'])) {
                $places = [];
                foreach ($response['features'] as $feature) {
                    $props = $feature['properties'] ?? [];
                    $geom = $feature['geometry'] ?? [];
                    
                    if (empty($props['name']) || empty($geom['coordinates'])) {
                        continue;
                    }
                    
                    $xid = $props['xid'] ?? '';
                    $placeLat = (float)$geom['coordinates'][1];
                    $placeLon = (float)$geom['coordinates'][0];
                    $placeKinds = $props['kinds'] ?? '';
                    
                    // Synthesize properties dynamically using stable hashing based on Name
                    $hashVal = abs(crc32($props['name']));
                    
                    if (strpos($kinds, 'accommodation') !== false) {
                        $types = ['Hotel', 'Resort', 'Boutique Hotel', 'Guesthouse', 'Lodge'];
                        $type = $types[$hashVal % count($types)];
                        $price = 850.00 + ($hashVal % 3600); // Sensible ZAR range R850 - R4450
                        $stars = 3 + ($hashVal % 3); // 3-5 stars
                        
                        $places[] = [
                            'name' => $props['name'],
                            'type' => $type,
                            'price' => round($price, 2),
                            'rating' => $stars,
                            'lat' => $placeLat,
                            'lon' => $placeLon,
                            'address' => "Near Center, " . ($props['osm'] ?? 'OTM Area')
                        ];
                    } elseif (strpos($kinds, 'catering') !== false) {
                        $cuisines = ['Local Fusion', 'International', 'Bistro', 'Traditional', 'Seafood', 'Fine Dining'];
                        $cuisine = $cuisines[$hashVal % count($cuisines)];
                        $cost = 120.00 + ($hashVal % 480); // Sensible ZAR range R120 - R600
                        
                        $places[] = [
                            'name' => $props['name'],
                            'cuisine' => $cuisine,
                            'cost' => round($cost, 2),
                            'lat' => $placeLat,
                            'lon' => $placeLon
                        ];
                    } else {
                        $categories = ['Historic Site', 'Monument', 'Art Gallery', 'Nature Park', 'Museum', 'Scenic View'];
                        $category = $categories[$hashVal % count($categories)];
                        $fee = ($hashVal % 5 === 0) ? 0.00 : 40.00 + ($hashVal % 280); // Sensible ZAR range Free to R320
                        
                        $places[] = [
                            'name' => $props['name'],
                            'category' => $category,
                            'fee' => round($fee, 2),
                            'lat' => $placeLat,
                            'lon' => $placeLon
                        ];
                    }
                }
                
                if (!empty($places)) {
                    return $places;
                }
            }
        }

        // Complete backup fallback: return default South African sample dataset
        return $this->getDefaultBackupPlaces($kinds, $limit);
    }

    /**
     * Simulates real flight route datasets from aviation databases between standard airports in Rands (ZAR)
     */
    public function getFlightsBetweenAirports($depCode, $arrCode) {
        $depCode = strtoupper(trim($depCode));
        $arrCode = strtoupper(trim($arrCode));
        
        // Define known premium airport database
        $airports = [
            'JNB' => 'OR Tambo International (Johannesburg, ZA)',
            'CPT' => 'Cape Town International (Cape Town, ZA)',
            'DUR' => 'King Shaka International (Durban, ZA)',
            'LHR' => 'London Heathrow (London, UK)',
            'CDG' => 'Charles de Gaulle (Paris, FR)',
            'FCO' => 'Leonardo da Vinci-Fiumicino (Rome, IT)',
            'HND' => 'Haneda Airport (Tokyo, JP)'
        ];

        // Ensure airport codes are recognized, fallback if not
        $depName = $airports[$depCode] ?? "$depCode Airport";
        $arrName = $airports[$arrCode] ?? "$arrCode Airport";

        // Generate flight scheduling
        $airlines = [
            'South African Airways' => ['code' => 'SA', 'base_cost' => 1200],
            'British Airways' => ['code' => 'BA', 'base_cost' => 9800],
            'Air France' => ['code' => 'AF', 'base_cost' => 10200],
            'Japan Airlines' => ['code' => 'JL', 'base_cost' => 14000],
            'Lufthansa' => ['code' => 'LH', 'base_cost' => 10500],
            'Qatar Airways' => ['code' => 'QR', 'base_cost' => 8800],
            'Emirates' => ['code' => 'EK', 'base_cost' => 9200],
            'FlySafair' => ['code' => 'FA', 'base_cost' => 1100] // Domestic SA only
        ];

        // Determine if domestic route (within South Africa)
        $isDomestic = in_array($depCode, ['JNB', 'CPT', 'DUR']) && in_array($arrCode, ['JNB', 'CPT', 'DUR']);
        
        $flights = [];
        $seedRandom = abs(crc32($depCode . '-' . $arrCode));
        
        // Generate 3 flight alternatives (Morning, Afternoon, Red-eye/Evening)
        $periods = [
            ['time' => '08:15:00', 'hours' => ($isDomestic ? 2 : 11)],
            ['time' => '14:30:00', 'hours' => ($isDomestic ? 2 : 13)],
            ['time' => '21:00:00', 'hours' => ($isDomestic ? 2.5 : 12)]
        ];

        foreach ($periods as $idx => $p) {
            $currentSeed = $seedRandom + $idx;
            
            // Choose appropriate airline
            $eligibleAirlines = [];
            foreach ($airlines as $name => $meta) {
                if ($isDomestic) {
                    if ($meta['code'] === 'SA' || $meta['code'] === 'FA') {
                        $eligibleAirlines[$name] = $meta;
                    }
                } else {
                    if ($meta['code'] !== 'FA') { // FlySafair is domestic only
                        $eligibleAirlines[$name] = $meta;
                    }
                }
            }
            if (empty($eligibleAirlines)) {
                $eligibleAirlines = $airlines;
            }
            
            $airlineKeys = array_keys($eligibleAirlines);
            $selectedAirlineName = $airlineKeys[$currentSeed % count($airlineKeys)];
            $airlineMeta = $eligibleAirlines[$selectedAirlineName];
            
            $flightNum = $airlineMeta['code'] . (100 + ($currentSeed % 899));
            
            // Cost calculation: Base + variance in Rands (ZAR)
            $multiplier = 1.0 + (($currentSeed % 30) / 100.0); // +0% to +30%
            $cost = $airlineMeta['base_cost'] * $multiplier;
            
            if ($isDomestic && $depCode === $arrCode) {
                continue; // Can't fly to the same airport
            }

            // Dates calculation (e.g. departing 2 days from now)
            $depDateTime = new DateTime();
            $depDateTime->modify('+' . (2 + ($currentSeed % 5)) . ' days');
            $depDateTime->setTime(substr($p['time'], 0, 2), substr($p['time'], 3, 2), 0);
            
            $arrDateTime = clone $depDateTime;
            $durationMinutes = (int)($p['hours'] * 60);
            $arrDateTime->modify("+{$durationMinutes} minutes");

            $flights[] = [
                'airline' => $selectedAirlineName,
                'flightNum' => $flightNum,
                'depTime' => $depDateTime->format('Y-m-d H:i:s'),
                'arrTime' => $arrDateTime->format('Y-m-d H:i:s'),
                'cost' => round($cost, 2),
                'depCode' => $depCode,
                'depName' => $depName,
                'arrCode' => $arrCode,
                'arrName' => $arrName
            ];
        }

        return $flights;
    }

    /**
     * Curl fetch helper with timeout
     */
    private function fetchUrl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Tripistry Premium Travel Portal');
        
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $output) {
            return json_decode($output, true);
        }
        return null;
    }

    /**
     * Helper to find nearest fallback city
     */
    private function findNearestFallbackCity($lat, $lon) {
        $thresholdDist = 0.8; // Latitude/Longitude degrees bounding limit (~80km)
        foreach ($this->localFallbackData as $key => $data) {
            $dist = sqrt(pow($data['lat'] - $lat, 2) + pow($data['lon'] - $lon, 2));
            if ($dist <= $thresholdDist) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Complete emergency backup places in South African Rands
     */
    private function getDefaultBackupPlaces($kinds, $limit) {
        $capeTown = $this->localFallbackData['cape town'];
        if (strpos($kinds, 'accommodation') !== false) {
            return array_slice($capeTown['accommodation'], 0, $limit);
        } elseif (strpos($kinds, 'catering') !== false) {
            return array_slice($capeTown['restaurants'], 0, $limit);
        } else {
            return array_slice($capeTown['attractions'], 0, $limit);
        }
    }

    /**
     * Initialize high-quality pre-cached local database
     */
    private function initializeFallbackData() {
        $this->localFallbackData = [
            'cape town' => [
                'name' => 'Cape Town',
                'country' => 'ZA',
                'lat' => -33.9249,
                'lon' => 18.4241,
                'accommodation' => [
                    [
                        'name' => 'The Silo Hotel',
                        'type' => 'Luxury Hotel',
                        'price' => 4500.00,
                        'rating' => 5,
                        'address' => 'Silo Square, V&A Waterfront, Cape Town',
                        'lat' => -33.9069,
                        'lon' => 18.4231
                    ],
                    [
                        'name' => 'Table Mountain Guesthouse',
                        'type' => 'Guesthouse',
                        'price' => 1200.00,
                        'rating' => 4,
                        'address' => '24 Kloof Road, Tamboerskloof, Cape Town',
                        'lat' => -33.9310,
                        'lon' => 18.4060
                    ],
                    [
                        'name' => 'Camps Bay Beach Resort',
                        'type' => 'Resort',
                        'price' => 2800.00,
                        'rating' => 5,
                        'address' => 'Victoria Road, Camps Bay, Cape Town',
                        'lat' => -33.9515,
                        'lon' => 18.3780
                    ]
                ],
                'attractions' => [
                    [
                        'name' => 'Table Mountain Aerial Cableway',
                        'category' => 'Scenic View',
                        'fee' => 380.00,
                        'lat' => -33.9628,
                        'lon' => 18.4031
                    ],
                    [
                        'name' => 'Robben Island Museum',
                        'category' => 'Historic Site',
                        'fee' => 400.00,
                        'lat' => -33.8076,
                        'lon' => 18.3722
                    ],
                    [
                        'name' => 'Kirstenbosch National Botanical Garden',
                        'category' => 'Nature Park',
                        'fee' => 220.00,
                        'lat' => -33.9903,
                        'lon' => 18.4325
                    ]
                ],
                'restaurants' => [
                    [
                        'name' => 'The Test Kitchen Fledgelings',
                        'cuisine' => 'Fine Dining',
                        'cost' => 650.00,
                        'lat' => -33.9275,
                        'lon' => 18.4610
                    ],
                    [
                        'name' => 'Codfather Seafood & Sushi',
                        'cuisine' => 'Seafood',
                        'cost' => 350.00,
                        'lat' => -33.9540,
                        'lon' => 18.3788
                    ],
                    [
                        'name' => 'Eastern Food Bazaar',
                        'cuisine' => 'Local Fusion',
                        'cost' => 95.00,
                        'lat' => -33.9252,
                        'lon' => 18.4215
                    ]
                ]
            ],
            'paris' => [
                'name' => 'Paris',
                'country' => 'FR',
                'lat' => 48.8566,
                'lon' => 2.3522,
                'accommodation' => [
                    [
                        'name' => 'Hotel Plaza Athénée',
                        'type' => 'Luxury Hotel',
                        'price' => 5200.00,
                        'rating' => 5,
                        'address' => '25 Avenue Montaigne, Paris',
                        'lat' => 48.8662,
                        'lon' => 2.3032
                    ],
                    [
                        'name' => 'Le Marais Boutique Apartment',
                        'type' => 'Boutique Hotel',
                        'price' => 2100.00,
                        'rating' => 4,
                        'address' => '14 Rue des Rosiers, Paris',
                        'lat' => 48.8578,
                        'lon' => 2.3590
                    ],
                    [
                        'name' => 'Generator Hostel Paris',
                        'type' => 'Hostel',
                        'price' => 650.00,
                        'rating' => 3,
                        'address' => '9-11 Place du Colonel Fabien, Paris',
                        'lat' => 48.8785,
                        'lon' => 2.3708
                    ]
                ],
                'attractions' => [
                    [
                        'name' => 'Eiffel Tower',
                        'category' => 'Monument',
                        'fee' => 350.00,
                        'lat' => 48.8584,
                        'lon' => 2.2945
                    ],
                    [
                        'name' => 'Louvre Museum',
                        'category' => 'Art Gallery',
                        'fee' => 300.00,
                        'lat' => 48.8606,
                        'lon' => 2.3376
                    ],
                    [
                        'name' => 'Cathédrale Notre-Dame de Paris',
                        'category' => 'Historic Site',
                        'fee' => 0.00,
                        'lat' => 48.8530,
                        'lon' => 2.3499
                    ]
                ],
                'restaurants' => [
                    [
                        'name' => 'Le Jules Verne',
                        'cuisine' => 'French Fine Dining',
                        'cost' => 850.00,
                        'lat' => 48.8584,
                        'lon' => 2.2945
                    ],
                    [
                        'name' => 'Café de Flore',
                        'cuisine' => 'Bistro',
                        'cost' => 280.00,
                        'lat' => 48.8542,
                        'lon' => 2.3287
                    ],
                    [
                        'name' => 'L\'As du Fallafel',
                        'cuisine' => 'Traditional',
                        'cost' => 120.00,
                        'lat' => 48.8575,
                        'lon' => 2.3592
                    ]
                ]
            ],
            'rome' => [
                'name' => 'Rome',
                'country' => 'IT',
                'lat' => 41.9028,
                'lon' => 12.4964,
                'accommodation' => [
                    [
                        'name' => 'Hotel de Russie',
                        'type' => 'Luxury Hotel',
                        'price' => 4800.00,
                        'rating' => 5,
                        'address' => 'Via del Babuino 9, Rome',
                        'lat' => 41.9103,
                        'lon' => 12.4764
                    ],
                    [
                        'name' => 'Colosseum View Palace',
                        'type' => 'Boutique Hotel',
                        'price' => 1800.00,
                        'rating' => 4,
                        'address' => 'Via Labicana 125, Rome',
                        'lat' => 41.8906,
                        'lon' => 12.4950
                    ],
                    [
                        'name' => 'Navona Guest Suite',
                        'type' => 'Guesthouse',
                        'price' => 1350.00,
                        'rating' => 4,
                        'address' => 'Piazza Navona 42, Rome',
                        'lat' => 41.8988,
                        'lon' => 12.4731
                    ]
                ],
                'attractions' => [
                    [
                        'name' => 'Colosseum & Roman Forum',
                        'category' => 'Historic Site',
                        'fee' => 280.00,
                        'lat' => 41.8902,
                        'lon' => 12.4922
                    ],
                    [
                        'name' => 'Trevi Fountain',
                        'category' => 'Monument',
                        'fee' => 0.00,
                        'lat' => 41.9009,
                        'lon' => 12.4833
                    ],
                    [
                        'name' => 'Vatican Museums & Sistine Chapel',
                        'category' => 'Art Gallery',
                        'fee' => 320.00,
                        'lat' => 41.9068,
                        'lon' => 12.4534
                    ]
                ],
                'restaurants' => [
                    [
                        'name' => 'La Pergola',
                        'cuisine' => 'Fine Dining',
                        'cost' => 950.00,
                        'lat' => 41.9198,
                        'lon' => 12.4452
                    ],
                    [
                        'name' => 'Cantina e Cucina',
                        'cuisine' => 'Traditional Italian',
                        'cost' => 240.00,
                        'lat' => 41.8982,
                        'lon' => 12.4715
                    ],
                    [
                        'name' => 'Pizzeria da Remo',
                        'cuisine' => 'Bistro',
                        'cost' => 140.00,
                        'lat' => 41.8795,
                        'lon' => 12.4789
                    ]
                ]
            ],
            'tokyo' => [
                'name' => 'Tokyo',
                'country' => 'JP',
                'lat' => 35.6762,
                'lon' => 139.6503,
                'accommodation' => [
                    [
                        'name' => 'Park Hyatt Tokyo',
                        'type' => 'Luxury Hotel',
                        'price' => 5500.00,
                        'rating' => 5,
                        'address' => '3-7-1-2 Nishi-Shinjuku, Tokyo',
                        'lat' => 35.6852,
                        'lon' => 139.6912
                    ],
                    [
                        'name' => 'Shibuya Capsule Pods',
                        'type' => 'Hostel',
                        'price' => 550.00,
                        'rating' => 3,
                        'address' => '1-19-1 Shibuya, Tokyo',
                        'lat' => 35.6601,
                        'lon' => 139.7020
                    ],
                    [
                        'name' => 'Ryokan Asakusa Shigetsu',
                        'type' => 'Guesthouse',
                        'price' => 1950.00,
                        'rating' => 4,
                        'address' => '1-31-11 Asakusa, Tokyo',
                        'lat' => 35.7132,
                        'lon' => 139.7958
                    ]
                ],
                'attractions' => [
                    [
                        'name' => 'Sensō-ji Temple',
                        'category' => 'Historic Site',
                        'fee' => 0.00,
                        'lat' => 35.7148,
                        'lon' => 139.7967
                    ],
                    [
                        'name' => 'Tokyo Skytree',
                        'category' => 'Monument',
                        'fee' => 380.00,
                        'lat' => 35.7101,
                        'lon' => 139.8107
                    ],
                    [
                        'name' => 'Shinjuku Gyoen National Garden',
                        'category' => 'Nature Park',
                        'fee' => 80.00,
                        'lat' => 35.6852,
                        'lon' => 139.7101
                    ]
                ],
                'restaurants' => [
                    [
                        'name' => 'Sukiyabashi Jiro',
                        'cuisine' => 'Fine Dining',
                        'cost' => 1100.00,
                        'lat' => 35.6723,
                        'lon' => 139.7645
                    ],
                    [
                        'name' => 'Ichiran Ramen Shibuya',
                        'cuisine' => 'Traditional Japanese',
                        'cost' => 150.00,
                        'lat' => 35.6618,
                        'lon' => 139.7011
                    ],
                    [
                        'name' => 'Rokurinsha Tsukemen',
                        'cuisine' => 'Bistro',
                        'cost' => 130.00,
                        'lat' => 35.6813,
                        'lon' => 139.7671
                    ]
                ]
            ],
            'london' => [
                'name' => 'London',
                'country' => 'UK',
                'lat' => 51.5074,
                'lon' => -0.1278,
                'accommodation' => [
                    [
                        'name' => 'The Ritz London',
                        'type' => 'Luxury Hotel',
                        'price' => 5800.00,
                        'rating' => 5,
                        'address' => '150 Piccadilly, St. James\'s, London',
                        'lat' => 51.5071,
                        'lon' => -0.1416
                    ],
                    [
                        'name' => 'Soho Boutique Hotel',
                        'type' => 'Boutique Hotel',
                        'price' => 2400.00,
                        'rating' => 4,
                        'address' => '25 Wardour Street, Soho, London',
                        'lat' => 51.5122,
                        'lon' => -0.1319
                    ],
                    [
                        'name' => 'Wombat\'s City Hostel',
                        'type' => 'Hostel',
                        'price' => 700.00,
                        'rating' => 3,
                        'address' => '7 Dock Street, Whitechapel, London',
                        'lat' => 51.5098,
                        'lon' => -0.0699
                    ]
                ],
                'attractions' => [
                    [
                        'name' => 'The British Museum',
                        'category' => 'Museum',
                        'fee' => 0.00,
                        'lat' => 51.5194,
                        'lon' => -0.1270
                    ],
                    [
                        'name' => 'Tower of London & Crown Jewels',
                        'category' => 'Historic Site',
                        'fee' => 390.00,
                        'lat' => 51.5081,
                        'lon' => -0.0759
                    ],
                    [
                        'name' => 'The London Eye',
                        'category' => 'Monument',
                        'fee' => 420.00,
                        'lat' => 51.5033,
                        'lon' => -0.1195
                    ]
                ],
                'restaurants' => [
                    [
                        'name' => 'Restaurant Gordon Ramsay',
                        'cuisine' => 'French Fine Dining',
                        'cost' => 980.00,
                        'lat' => 48.8606, // adjusted coordinates relative to Chelsea
                        'lon' => 2.3376
                    ],
                    [
                        'name' => 'Dishoom Covent Garden',
                        'cuisine' => 'Indian Fusion',
                        'cost' => 280.00,
                        'lat' => 51.5126,
                        'lon' => -0.1265
                    ],
                    [
                        'name' => 'The Golden Hind',
                        'cuisine' => 'Traditional Fish & Chips',
                        'cost' => 160.00,
                        'lat' => 51.5165,
                        'lon' => -0.1511
                    ]
                ]
            ]
        ];

        // Ensure Gordon Ramsay London coordinates are precise
        $this->localFallbackData['london']['restaurants'][0]['lat'] = 51.4854;
        $this->localFallbackData['london']['restaurants'][0]['lon'] = -0.1621;
    }
}
?>
