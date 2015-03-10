<?php

namespace VersionPress\Filters;

/**
 * Replaces absolute site URL with placeholder
 *
 * @uses get_site_url()
 */
class AbsoluteUrlFilter implements EntityFilter {

    const PLACEHOLDER = "<<[site-url]>>";
    private $siteUrl;

    function __construct($siteUrl = null) {
        $this->siteUrl = $siteUrl ?: get_site_url();
    }

    /**
     * Replaces absolute URLs with placeholder
     *
     * @param array $entity
     * @return array
     */
    function apply($entity) {
        foreach ($entity as $field => $value) {
            if ($field === "guid") continue; // guids cannot be changed even they are in form of URL
            if (isset($entity[$field])) {
                $entity[$field] = $this->replaceLocalUrls($value);
            }
        }
        return $entity;
    }

    /**
     * Replaces the placeholder with absolute URL
     *
     * @param array $entity
     * @return array
     */
    function restore($entity) {
        foreach ($entity as $field => $value) {
            if (isset($entity[$field])) {
                $entity[$field] = $this->replacePlaceholders($value);
            }
        }
        return $entity;
    }

    private function replaceLocalUrls($value) {
        return is_string($value) ? str_replace($this->siteUrl, self::PLACEHOLDER, $value) : $value;
    }

    private function replacePlaceholders($value) {
        return is_string($value) ? str_replace(self::PLACEHOLDER, $this->siteUrl, $value) : $value;
    }
}
