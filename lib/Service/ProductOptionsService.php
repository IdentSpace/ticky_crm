<?php

namespace OCA\TickyCRM\Service;

use InvalidArgumentException;
use OCP\IConfig;
use OCP\L10N\IFactory;

class ProductOptionsService {
    public const FIELDS = ['category', 'type', 'procurement_type', 'status', 'unit', 'currency'];
    public const CONFIG_KEY = 'product_options';

    public function __construct(private IConfig $config, private IFactory $l10nFactory) {
    }

    public function defaults(): array {
        $l10n = $this->l10nFactory->get('ticky_crm');
        $definitions = [
            'category' => ['Hardware' => 'product_category_hardware', 'Software' => 'product_category_software', 'Consulting' => 'product_category_consulting', 'Other' => 'product_category_other'],
            'type' => ['product' => 'product_option_product', 'service' => 'product_option_service', 'digital' => 'product_option_digital'],
            'procurement_type' => ['in_house' => 'product_option_in_house', 'external' => 'product_option_external'],
            'status' => ['active' => 'product_option_active', 'archived' => 'product_option_archived'],
            'unit' => ['piece' => 'product_unit_piece', 'hour' => 'product_unit_hour', 'day' => 'product_unit_day', 'month' => 'product_unit_month', 'kg' => 'product_unit_kg', 'm' => 'product_unit_m'],
            'currency' => ['EUR' => 'EUR', 'USD' => 'USD', 'CHF' => 'CHF', 'GBP' => 'GBP'],
        ];
        $options = [];
        foreach ($definitions as $field => $values) {
            $options[$field] = [];
            foreach ($values as $id => $label) {
                $options[$field][] = ['id' => $id, 'label' => $l10n->t($label)];
            }
        }
        return $options;
    }

    public function get(): array {
        $saved = json_decode($this->config->getAppValue('ticky_crm', self::CONFIG_KEY, '{}'), true);
        return array_replace($this->defaults(), is_array($saved) ? array_intersect_key($saved, array_flip(self::FIELDS)) : []);
    }

    public function save(array $options): array {
        $validated = [];
        foreach (self::FIELDS as $field) {
            $entries = $options[$field] ?? null;
            if (!is_array($entries) || !array_is_list($entries) || count($entries) > 100 || ($entries === [] && !in_array($field, ['category', 'procurement_type'], true))) {
                throw new InvalidArgumentException($field);
            }
            $ids = [];
            $validated[$field] = [];
            foreach ($entries as $entry) {
                if (!is_array($entry) || !is_string($entry['id'] ?? null) || !is_string($entry['label'] ?? null)) {
                    throw new InvalidArgumentException($field);
                }
                $id = trim($entry['id']);
                $label = trim($entry['label']);
                $maxLength = $field === 'category' ? 255 : ($field === 'currency' ? 3 : 32);
                if ($id === '' || mb_strlen($id) > $maxLength || $label === '' || mb_strlen($label) > 128 || in_array($id, $ids, true)) {
                    throw new InvalidArgumentException($field);
                }
                if ($field === 'currency' && !preg_match('/^[A-Z]{3}$/D', $id)) {
                    throw new InvalidArgumentException($field);
                }
                if (in_array($field, ['type', 'procurement_type', 'status'], true) && !preg_match('/^[a-z][a-z0-9_]*$/D', $id)) {
                    throw new InvalidArgumentException($field);
                }
                $ids[] = $id;
                $validated[$field][] = ['id' => $id, 'label' => $label];
            }
            if ($field === 'status' && (!in_array('active', $ids, true) || !in_array('archived', $ids, true))) {
                throw new InvalidArgumentException($field);
            }
        }
        $this->config->setAppValue('ticky_crm', self::CONFIG_KEY, json_encode($validated, JSON_THROW_ON_ERROR));
        return $validated;
    }

    public function accepts(string $field, ?string $value, ?string $previous = null): bool {
        if ($value === null) {
            return in_array($field, ['category', 'procurement_type'], true);
        }
        // Retired values remain valid on unchanged existing records.
        return $value === $previous || in_array($value, array_column($this->get()[$field], 'id'), true);
    }
}
