<?php
class Location
{
    private int $id;
    private string $city;
    private string $address;
    private string $zip_code;
    private string $country_code;
    private string $phone_number;

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of city
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set the value of city
     *
     * @return  self
     */
    public function setCity($city)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get the value of address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set the value of address
     *
     * @return  self
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get the value of zip_code
     */
    public function getZip_code()
    {
        return $this->zip_code;
    }

    /**
     * Set the value of zip_code
     *
     * @return  self
     */
    public function setZip_code($zip_code)
    {
        $this->zip_code = $zip_code;

        return $this;
    }

    /**
     * Get the value of country_code
     */
    public function getCountry_code()
    {
        return $this->country_code;
    }

    /**
     * Set the value of country_code
     *
     * @return  self
     */
    public function setCountry_code($country_code)
    {
        $this->country_code = $country_code;

        return $this;
    }

    /**
     * Get the value of phone_number
     */
    public function getPhone_number()
    {
        return $this->phone_number;
    }

    /**
     * Set the value of phone_number
     *
     * @return  self
     */
    public function setPhone_number($phone_number)
    {
        $this->phone_number = $phone_number;

        return $this;
    }
}
