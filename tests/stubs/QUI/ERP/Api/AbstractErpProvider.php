<?php

namespace QUI\ERP\Api;

use QUI\Controls\Sitemap\Map;

if (!class_exists(AbstractErpProvider::class, false)) {
    abstract class AbstractErpProvider
    {
        public static function addMenuItems(Map $Map): void
        {
        }

        /**
         * @return array<mixed>
         */
        public static function getNumberRanges(): array
        {
            return [];
        }

        /**
         * @return array<mixed>
         */
        public static function getMailLocale(): array
        {
            return [];
        }
    }
}
