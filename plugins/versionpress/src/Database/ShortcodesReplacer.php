<?php

namespace VersionPress\Database;

use VersionPress\Utils\WordPressMissingFunctions;

class ShortcodesReplacer {
    /** @var ShortcodesInfo */
    private $shortcodesInfo;
    /** @var VpidRepository */
    private $vpidRepository;

    /**
     * @param ShortcodesInfo $shortcodeInfo
     * @param VpidRepository $vpidRepository
     */
    public function __construct(ShortcodesInfo $shortcodeInfo, VpidRepository $vpidRepository) {
        $this->shortcodesInfo = $shortcodeInfo;
        $this->vpidRepository = $vpidRepository;
    }

    public function replaceShortcodes($string) {
        $pattern = get_shortcode_regex($this->shortcodesInfo->getAllShortcodeNames());
        return preg_replace_callback("/$pattern/", $this->createReplaceCallback(array($this, 'getVpidByEntityNameAndId')), $string);
    }

    public function restoreShortcodes($string) {
        $pattern = get_shortcode_regex($this->shortcodesInfo->getAllShortcodeNames());
        return preg_replace_callback("/$pattern/", $this->createReplaceCallback(array($this, 'getIdByVpid')), $string);
    }

    public function replaceShortcodesInEntity($entityName, $entity) {
        if (!$this->entityCanContainShortcodes($entityName)) {
            return $entity;
        }

        foreach ($entity as $field => $value) {
            if ($this->fieldCanContainShortcodes($entityName, $field)) {
                $entity[$field] = $this->replaceShortcodes($value);
            }
        }

        return $entity;
    }

    public function restoreShortcodesInEntity($entityName, $entity) {
        if (!$this->entityCanContainShortcodes($entityName)) {
            return $entity;
        }

        foreach ($entity as $field => $value) {
            if ($this->fieldCanContainShortcodes($entityName, $field)) {
                $entity[$field] = $this->restoreShortcodes($value);
            }
        }

        return $entity;
    }

    public function entityCanContainShortcodes($entityName) {
        $shortcodeLocations = $this->shortcodesInfo->getShortcodeLocations();
        return isset($shortcodeLocations[$entityName]);
    }

    public function fieldCanContainShortcodes($entityName, $field) {
        if (!$this->entityCanContainShortcodes($entityName)) {
            return false;
        }

        $shortcodeLocations = $this->shortcodesInfo->getShortcodeLocations();
        $allowedFields = $shortcodeLocations[$entityName]['fields'];

        return array_search($field, $allowedFields) !== false;
    }

    private function createReplaceCallback($idProvider) {
        $shortcodesInfo = $this->shortcodesInfo;

        return function ($m) use ($shortcodesInfo, $idProvider) {
            // allow [[foo]] syntax for escaping a tag
            if ($m[1] == '[' && $m[6] == ']') {
                return substr($m[0], 1, -1);
            }

            $shortcodeTag = $m[2];
            $shortcodeInfo = $shortcodesInfo->getShortcodeInfo($shortcodeTag);
            $attributes = shortcode_parse_atts($m[3]);

            foreach ($attributes as $attribute => $value) {
                if (isset($shortcodeInfo[$attribute])) {
                    $ids = explode(',', $value);
                    $entityName = $shortcodeInfo[$attribute];
                    $attributes[$attribute] = join(',', array_map(function ($id) use ($entityName, $idProvider) { return $idProvider($entityName, $id); }, $ids));
                }
            }

            return WordPressMissingFunctions::renderShortcode($shortcodeTag, $attributes);
        };
    }

    private function getVpidByEntityNameAndId($entityName, $id) {
        return $this->vpidRepository->getVpidForEntity($entityName, $id) ?: $id;
    }

    private function getIdByVpid($entityName, $vpid) {
        return $this->vpidRepository->getIdForVpid($vpid) ?: $vpid;
    }
}
