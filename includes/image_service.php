<?php
/**
 * Tripistry Premium Image Service
 * Provides gorgeous, harmonized Unsplash photography CDN assets
 * with progressive fallback keyword matching to keep the app visually alive.
 */

class ImageService {
    // Rich dictionary of ultra-premium Unsplash photo assets with comprehensive search tags
    private static $stockLibrary = [
        // DESTINATIONS
        [
            'url' => 'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['cape town', 'table mountain', 'south africa', 'za', 'cpt', 'clifton', 'camps bay'],
            'category' => 'Destinations',
            'title' => 'Table Mountain, Cape Town'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['paris', 'france', 'eiffel', 'louvre', 'seine', 'romantic', 'europe'],
            'category' => 'Destinations',
            'title' => 'Eiffel Tower, Paris'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['rome', 'colosseum', 'italy', 'roman', 'ruins', 'fountain', 'history'],
            'category' => 'Destinations',
            'title' => 'Colosseum, Rome'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['london', 'big ben', 'uk', 'england', 'tower bridge', 'thames'],
            'category' => 'Destinations',
            'title' => 'Big Ben, London'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['tokyo', 'japan', 'shibuya', 'fuji', 'cherry blossoms', 'kyoto', 'asia'],
            'category' => 'Destinations',
            'title' => 'Shibuya Crossing, Tokyo'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1545569341-9eb8b30979d9?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['kyoto', 'shrine', 'bamboo', 'temple', 'japan'],
            'category' => 'Destinations',
            'title' => 'Fushimi Inari, Kyoto'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['maldives', 'tropical', 'bora bora', 'beach', 'island', 'paradise', 'resort'],
            'category' => 'Destinations',
            'title' => 'Tropical Resort Maldives'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['sydney', 'australia', 'opera house', 'harbour'],
            'category' => 'Destinations',
            'title' => 'Sydney Opera House'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['durban', 'beachfront', 'umhlanga', 'south africa', 'dur'],
            'category' => 'Destinations',
            'title' => 'Umhlanga Pier, Durban'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['johannesburg', 'joburg', 'gauteng', 'cityscape', 'sandton', 'jnb'],
            'category' => 'Destinations',
            'title' => 'Sandton City, Johannesburg'
        ],

        // ACCOMMODATIONS & STAYS
        [
            'url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80',
            'tags' => ['hotel', 'luxury hotel', 'stay', 'accommodations', 'premium stay', 'lobby', '5-star'],
            'category' => 'Stays',
            'title' => 'Luxury Hotel Lobby'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=800&q=80',
            'tags' => ['bedroom', 'suite', 'room', 'bed', 'boutique room', 'stay interior'],
            'category' => 'Stays',
            'title' => 'Boutique Master Suite'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=800&q=80',
            'tags' => ['resort', 'beach resort', 'pool', 'villa', 'vacation villa', 'swimming pool'],
            'category' => 'Stays',
            'title' => 'Infinity Pool Resort'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&w=800&q=80',
            'tags' => ['cabin', 'lodge', 'cozy', 'forest lodge', 'wood cabin', 'mountain stay'],
            'category' => 'Stays',
            'title' => 'Rustic Forest Cabin'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80',
            'tags' => ['apartment', 'loft', 'studio', 'flat', 'city apartment', 'modern stay'],
            'category' => 'Stays',
            'title' => 'Modern Urban Loft'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=800&q=80',
            'tags' => ['spa', 'wellness', 'massage', 'relax', 'retreat'],
            'category' => 'Stays',
            'title' => 'Luxury Wellness Spa'
        ],

        // SIGHTS & ATTRACTIONS
        [
            'url' => 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=800&q=80',
            'tags' => ['adventure', 'road trip', 'desert', 'canyon', 'hiking', 'sightseeing'],
            'category' => 'Sights',
            'title' => 'Canyon Exploration Roadtrip'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=800&q=80',
            'tags' => ['boat', 'river', 'lake', 'sailing', 'kayak', 'water', 'cruise'],
            'category' => 'Sights',
            'title' => 'Mountain Lake Sailing'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=800&q=80',
            'tags' => ['museum', 'gallery', 'art', 'exhibit', 'history', 'monument'],
            'category' => 'Sights',
            'title' => 'Grand Art Museum'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?auto=format&fit=crop&w=800&q=80',
            'tags' => ['park', 'garden', 'green', 'botanical', 'nature walk', 'trail'],
            'category' => 'Sights',
            'title' => 'Scenic Botanical Gardens'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?auto=format&fit=crop&w=800&q=80',
            'tags' => ['castle', 'palace', 'historic ruins', 'fortress', 'monument'],
            'category' => 'Sights',
            'title' => 'Historic Medieval Castle'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80',
            'tags' => ['beach', 'ocean', 'coast', 'sand', 'coastal landmark', 'sea view'],
            'category' => 'Sights',
            'title' => 'Sunlit Sandy Coastline'
        ],

        // RESTURANTS & CULINARY
        [
            'url' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?auto=format&fit=crop&w=800&q=80',
            'tags' => ['restaurant', 'dining', 'food', 'chef', 'dinner spot', 'eatery'],
            'category' => 'Dining',
            'title' => 'Chic Modern Restaurant'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1579871494447-9811cf80d66c?auto=format&fit=crop&w=800&q=80',
            'tags' => ['sushi', 'japanese dining', 'cuisine', 'sushi bar', 'ramen', 'seafood'],
            'category' => 'Dining',
            'title' => 'Premium Sushi Platter'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=800&q=80',
            'tags' => ['pizza', 'italian bistro', 'trattoria', 'cheese', 'pasta'],
            'category' => 'Dining',
            'title' => 'Woodfired Neapolitan Pizza'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?auto=format&fit=crop&w=800&q=80',
            'tags' => ['pasta', 'spaghetti', 'sauce', 'italian cuisine', 'noodle'],
            'category' => 'Dining',
            'title' => 'Gourmet Fettuccine Pasta'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1498804103079-a6351b050096?auto=format&fit=crop&w=800&q=80',
            'tags' => ['cafe', 'coffee', 'espresso', 'breakfast', 'brunch spot', 'cozy cafe'],
            'category' => 'Dining',
            'title' => 'Artisanal Coffee & Cafe'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=800&q=80',
            'tags' => ['fine dining', 'wine', 'candlelight', 'gourmet plate', 'culinary experience'],
            'category' => 'Dining',
            'title' => 'Upscale Candlelit Fine Dining'
        ],

        // ADVENTURE / GENERAL
        [
            'url' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['travel', 'explore', 'map', 'journey', 'adventure', 'vacation', 'tripistry'],
            'category' => 'Adventure',
            'title' => 'Exploration Compass & Map'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['mountains', 'lake', 'sunset', 'sky', 'scenic getaway'],
            'category' => 'Adventure',
            'title' => 'Sunset Mountain Vista'
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1527631746610-bca00a040d60?auto=format&fit=crop&w=1200&q=85',
            'tags' => ['backpack', 'hiking', 'trekking', 'nature trail', 'active adventure'],
            'category' => 'Adventure',
            'title' => 'Backpacking Wilderness Trail'
        ]
    ];

    /**
     * Helper to search stock image list using a string of space-separated keywords
     */
    public static function searchStock($query) {
        $keywords = preg_split('/\W+/', strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($keywords)) {
            return self::$stockLibrary;
        }

        $scoredImages = [];
        foreach (self::$stockLibrary as $item) {
            $score = 0;
            foreach ($keywords as $kw) {
                // Exact tag matches get higher points
                foreach ($item['tags'] as $tag) {
                    if ($tag === $kw) {
                        $score += 10;
                    } elseif (strpos($tag, $kw) !== false) {
                        $score += 3;
                    }
                }
                // Title matches
                if (strpos(strtolower($item['title']), $kw) !== false) {
                    $score += 5;
                }
                // Category matches
                if (strpos(strtolower($item['category']), $kw) !== false) {
                    $score += 2;
                }
            }

            if ($score > 0) {
                $item['match_score'] = $score;
                $scoredImages[] = $item;
            }
        }

        // Sort by match score descending
        usort($scoredImages, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        // If no matches, return full library so user always gets visual choices
        return !empty($scoredImages) ? $scoredImages : self::$stockLibrary;
    }

    /**
     * Resolves the best fitting visual image for a Travel Package
     */
    public static function getPackageImage($title, $description = '') {
        $searchSpace = $title . ' ' . $description;
        $matches = self::searchStock($searchSpace);
        return $matches[0]['url']; // Return the top scored match
    }

    /**
     * Resolves the best fitting image for a Destination
     */
    public static function getDestinationImage($name) {
        $matches = self::searchStock($name);
        return $matches[0]['url'];
    }

    /**
     * Resolves the best fitting image for Accommodation
     */
    public static function getAccommodationImage($name, $type = '') {
        $searchSpace = $name . ' ' . $type . ' stay lodging room';
        $matches = self::searchStock($searchSpace);
        return $matches[0]['url'];
    }

    /**
     * Resolves the best fitting image for Attraction
     */
    public static function getAttractionImage($name, $category = '') {
        $searchSpace = $name . ' ' . $category . ' sight landmark attraction';
        $matches = self::searchStock($searchSpace);
        return $matches[0]['url'];
    }

    /**
     * Resolves the best fitting image for Restaurant
     */
    public static function getRestaurantImage($name, $cuisine = '') {
        $searchSpace = $name . ' ' . $cuisine . ' dining restaurant culinary food';
        $matches = self::searchStock($searchSpace);
        return $matches[0]['url'];
    }

    /**
     * Retrieves all items categorized in the stock library
     */
    public static function getLibraryByCategories() {
        $grouped = [];
        foreach (self::$stockLibrary as $item) {
            $grouped[$item['category']][] = $item;
        }
        return $grouped;
    }
}
