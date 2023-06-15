<?php

namespace App\Repositories;

use App\Plugins\Http\Exceptions;
use App\Plugins\Di\Injectable;


#[\AllowDynamicProperties]
class LocationRepository extends Injectable
{

    /**
     * Create a new location or update existing and return the location ID.
     *
     * @param object $locationData
     * @return int
     */
    public function manageLocation(object $locationData): int
    {
        //checking if the location already exists in database
        $existingLocation = $this->getLocationId($locationData);

        //return its ID if it exists
        if ($existingLocation !== null) {
            return $existingLocation;
        }

        $sql = "INSERT INTO locations (city, address, zip_code, country_code, phone_number)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    city = VALUES(city), address = VALUES(address), zip_code = VALUES(zip_code),
                    country_code = VALUES(country_code), phone_number = VALUES(phone_number)";
        $params = [
            $locationData->city ?? null,
            $locationData->address ?? null,
            $locationData->zip_code ?? null,
            $locationData->country_code ?? null,
            $locationData->phone_number ?? null
        ];

        $result = $this->db->executeQuery($sql, $params);

        if ($result === 0) {
            throw new Exceptions\InternalServerError("Failed to create/update location.");
        }

        $locationId = $this->db->getLastInsertedId();

        return $locationId;
    }

    /**
     * Get the ID of an existing location if it exists.
     *
     * @param object $locationData
     * @return int|null
     */
    public function getLocationId(object $locationData): ?int
    {
        $sql = "SELECT id FROM locations WHERE city = ? AND address = ? AND zip_code = ? AND country_code = ? AND phone_number = ?";
        $params = [
            $locationData->city ?? null,
            $locationData->address ?? null,
            $locationData->zip_code ?? null,
            $locationData->country_code ?? null,
            $locationData->phone_number ?? null
        ];

        $result = $this->db->executeQuery2($sql, $params);

        if (!empty($result)) {
            return $result[0]['id'];
        }

        return null;
    }
}
