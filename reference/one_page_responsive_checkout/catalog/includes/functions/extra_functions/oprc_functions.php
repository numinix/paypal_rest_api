<?php
/**
 * supplies javascript to dynamically update the states/provinces list when the country is changed
 * TABLES: zones
 *
 * return string
 */
  function zen_oprc_normalize_encoding($value) {
    $normalized = $value;
    $is_utf8 = false;

    if (function_exists('mb_check_encoding')) {
      $is_utf8 = mb_check_encoding($normalized, 'UTF-8');
    } else {
      $is_utf8 = (@preg_match('//u', $normalized) === 1);
    }

    if (!$is_utf8) {
      if (function_exists('mb_convert_encoding')) {
        $normalized = mb_convert_encoding($normalized, 'UTF-8', 'ISO-8859-1');
      } elseif (function_exists('iconv')) {
        $converted = @iconv('ISO-8859-1', 'UTF-8//TRANSLIT', $normalized);
        if ($converted !== false) {
          $normalized = $converted;
        }
      }
    }

    $replacement_character = chr(0xEF) . chr(0xBF) . chr(0xBD);
    if (strpos($normalized, $replacement_character) !== false && function_exists('iconv')) {
      $converted = @iconv('ISO-8859-1', 'UTF-8//TRANSLIT', $value);
      if ($converted !== false) {
        $normalized = $converted;
      }
    }

    return $normalized;
  }

  if (!function_exists('oprc_delivery_debug_truncate')) {
    function oprc_delivery_debug_truncate($value, $maxLength = 200)
    {
      $stringValue = (string) $value;

      if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($stringValue, 'UTF-8') > $maxLength) {
          return mb_substr($stringValue, 0, $maxLength, 'UTF-8') . '...';
        }
      } elseif (strlen($stringValue) > $maxLength) {
        return substr($stringValue, 0, $maxLength) . '...';
      }

      return $stringValue;
    }
  }

  if (!function_exists('oprc_delivery_debug_log')) {
    function oprc_delivery_debug_log($message, array $context = [])
    {
      if (!defined('OPRC_DEBUG_MODE') || OPRC_DEBUG_MODE !== 'true') {
        return;
      }

      $payload = '';
      if (!empty($context)) {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false) {
          $encoded = '[unserializable context]';
        }
        if (strlen($encoded) > 2000) {
          $encoded = substr($encoded, 0, 2000) . '...';
        }
        $payload = ' | ' . $encoded;
      }

      error_log('OPRC delivery debug: ' . $message . $payload);
    }
  }

  if (!function_exists('oprc_delivery_debug_summarize_value')) {
    function oprc_delivery_debug_summarize_value($value, $maxDepth = 1, $maxItems = 5, $maxStringLength = 120)
    {
      if ($maxDepth < 0) {
        return '[max depth reached]';
      }

      if (is_array($value)) {
        $summary = [];
        $count = 0;

        foreach ($value as $key => $item) {
          if ($count >= $maxItems) {
            $summary['...'] = '[truncated]';
            break;
          }

          $summary[$key] = oprc_delivery_debug_summarize_value($item, $maxDepth - 1, $maxItems, $maxStringLength);
          $count++;
        }

        return $summary;
      }

      if ($value instanceof \DateTimeInterface) {
        return $value->format(\DateTimeInterface::ATOM);
      }

      if (is_object($value)) {
        $summary = ['__class' => get_class($value)];

        $properties = array_keys(get_object_vars($value));
        if (!empty($properties)) {
          $summary['__properties'] = array_slice($properties, 0, $maxItems);
          if (count($properties) > $maxItems) {
            $summary['__properties'][] = '...';
          }
        }

        if ($maxDepth > 0) {
          if ($value instanceof \ArrayAccess && method_exists($value, 'getArrayCopy')) {
            $summary['__data'] = oprc_delivery_debug_summarize_value($value->getArrayCopy(), $maxDepth - 1, $maxItems, $maxStringLength);
          } elseif ($value instanceof \Traversable) {
            $arrayCopy = [];
            $count = 0;
            foreach ($value as $itemKey => $itemValue) {
              if ($count >= $maxItems) {
                $arrayCopy['...'] = '[truncated]';
                break;
              }
              $arrayCopy[$itemKey] = oprc_delivery_debug_summarize_value($itemValue, $maxDepth - 1, $maxItems, $maxStringLength);
              $count++;
            }
            if (!empty($arrayCopy)) {
              $summary['__data'] = $arrayCopy;
            }
          }
        }

        return $summary;
      }

      if (is_string($value)) {
        return oprc_delivery_debug_truncate($value, $maxStringLength);
      }

      if (is_resource($value)) {
        return 'resource(' . get_resource_type($value) . ')';
      }

      return $value;
    }
  }

  if (!function_exists('oprc_delivery_debug_collect_globals')) {
    function oprc_delivery_debug_collect_globals($moduleId)
    {
      $matches = [];
      $partialMatches = [];
      $normalizedModuleId = is_string($moduleId) ? strtolower($moduleId) : '';

      foreach ($GLOBALS as $key => $value) {
        if (!is_object($value)) {
          continue;
        }

        $entry = ['key' => $key, 'class' => get_class($value)];

        foreach (['code', 'id', 'title', 'module'] as $property) {
          if (property_exists($value, $property)) {
            $entry[$property] = oprc_delivery_debug_truncate($value->$property);
          }
        }

        $publicProperties = array_keys(get_object_vars($value));
        if (!empty($publicProperties)) {
          $entry['properties'] = array_slice($publicProperties, 0, 5);
          if (count($publicProperties) > 5) {
            $entry['properties'][] = '...';
          }
        }

        $keyMatch = (is_string($key) && strcasecmp($moduleId, $key) === 0);
        $codeMatch = (isset($entry['code']) && strcasecmp($moduleId, (string) $value->code) === 0);
        $idMatch = (isset($entry['id']) && strcasecmp($moduleId, (string) $value->id) === 0);

        if ($keyMatch || $codeMatch || $idMatch) {
          $entry['match'] = 'exact';
          $matches[] = $entry;
        } elseif ($normalizedModuleId !== '') {
          $lowerKey = is_string($key) ? strtolower($key) : '';
          $codeValue = isset($entry['code']) ? strtolower((string) $entry['code']) : '';
          $idValue = isset($entry['id']) ? strtolower((string) $entry['id']) : '';

          if (
            ($lowerKey !== '' && strpos($lowerKey, $normalizedModuleId) !== false)
            || ($codeValue !== '' && strpos($codeValue, $normalizedModuleId) !== false)
            || ($idValue !== '' && strpos($idValue, $normalizedModuleId) !== false)
          ) {
            $entry['match'] = 'partial';
            $partialMatches[] = $entry;
          }
        }

        // Only return exact or partial matches, limit to prevent excessive logging
        if (count($matches) >= 3) {
          break;
        }
        if (count($partialMatches) >= 5 && empty($matches)) {
          break;
        }
      }

      if (!empty($matches)) {
        return $matches;
      }

      if (!empty($partialMatches)) {
        return $partialMatches;
      }

      // Return empty array instead of fallback to reduce log size
      return [];
    }
  }

  if (!function_exists('oprc_delivery_debug_collect_shipping_modules_state')) {
    function oprc_delivery_debug_collect_shipping_modules_state()
    {
      $state = [];

      if (isset($GLOBALS['shipping_modules']) && is_object($GLOBALS['shipping_modules'])) {
        $shippingModules = $GLOBALS['shipping_modules'];
        $state['class'] = get_class($shippingModules);

        if (method_exists($shippingModules, 'getInitializedModules')) {
          $state['initializedModules'] = $shippingModules->getInitializedModules();
        }

        if (isset($shippingModules->modules) && is_array($shippingModules->modules)) {
          $state['modules'] = array_values($shippingModules->modules);
        }

        if (isset($shippingModules->quotes) && is_array($shippingModules->quotes)) {
          $ids = [];
          foreach ($shippingModules->quotes as $quoteEntry) {
            if (is_array($quoteEntry) && isset($quoteEntry['id'])) {
              $ids[] = $quoteEntry['id'];
            }
          }
          if (!empty($ids)) {
            $state['shippingModuleQuoteIds'] = array_values(array_unique($ids));
          }
        }
      }

      if (isset($GLOBALS['quotes']) && is_array($GLOBALS['quotes'])) {
        $globalIds = [];
        foreach ($GLOBALS['quotes'] as $quoteEntry) {
          if (is_array($quoteEntry) && isset($quoteEntry['id'])) {
            $globalIds[] = $quoteEntry['id'];
          }
        }
        if (!empty($globalIds)) {
          $state['globalQuoteIds'] = array_values(array_unique($globalIds));
        }
      }

      return $state;
    }
  }

  if (!function_exists('oprc_is_field_required')) {
    function oprc_is_field_required($minLength)
    {
      return (int)$minLength > 0;
    }
  }

  if (!function_exists('oprc_required_indicator')) {
    function oprc_required_indicator($minLength, $text)
    {
      if (oprc_is_field_required($minLength) && zen_not_null($text)) {
        return '<span class="alert">' . $text . '</span>';
      }

      return '';
    }
  }

  if (!function_exists('oprc_render_html_snippet')) {
    function oprc_render_html_snippet($html, ?array $allowedTags = null)
    {
      if (!is_string($html) || $html === '') {
        return '';
      }

      $charset = defined('CHARSET') ? CHARSET : 'UTF-8';
      $decoded = html_entity_decode($html, ENT_QUOTES, $charset);

      if ($allowedTags === null) {
        $allowedTags = ['span', 'div', 'br', 'strong', 'em', 'b', 'i', 'small', 'p', 'sup', 'sub'];
      }

      $allowedTagString = '';
      foreach ($allowedTags as $tag) {
        $allowedTagString .= '<' . $tag . '>';
      }

      $sanitized = strip_tags($decoded, $allowedTagString);

      // Remove potentially dangerous inline event handlers.
      $sanitized = preg_replace("/\\son[a-z]+\\s*=\\s*(\"|').*?\\1/i", '', $sanitized);

      // Prevent javascript: URLs in attributes like href or src.
      $sanitized = preg_replace_callback(
        "/\\s(href|src)\\s*=\\s*(\"|')(.*?)\\2/i",
        function ($matches) {
          $attribute = strtolower($matches[1]);
          $quote = $matches[2];
          $value = trim($matches[3]);

          if (stripos($value, 'javascript:') === 0) {
            return '';
          }

          return ' ' . $attribute . '=' . $quote . $value . $quote;
        },
        $sanitized
      );

      return trim($sanitized);
    }
  }

  if (!function_exists('oprc_extract_delivery_update_from_quotes')) {
    function oprc_extract_delivery_update_from_quotes($quotes)
    {
      if (!is_array($quotes)) {
        return '';
      }

      $candidateKeys = [
        'moduleDate',
        'date',
        'deliveryDate',
        'delivery_date',
        'delivery',
        'estimatedDate',
        'estimated_date',
        'estimatedDelivery',
        'estimated_delivery',
        'eta',
        'estimate',
      ];

      foreach ($candidateKeys as $key) {
        if (!array_key_exists($key, $quotes)) {
          continue;
        }

        $value = $quotes[$key];
        if (is_string($value)) {
          $value = trim($value);
          if ($value !== '') {
            return $value;
          }
        }
      }

      if (isset($quotes['methods']) && is_array($quotes['methods'])) {
        foreach ($quotes['methods'] as $method) {
          if (!is_array($method)) {
            continue;
          }

          foreach ($candidateKeys as $key) {
            if (!array_key_exists($key, $method)) {
              continue;
            }

            $value = $method[$key];
            if (is_string($value)) {
              $value = trim($value);
              if ($value !== '') {
                return $value;
              }
            }
          }
        }
      }

      return '';
    }
  }

  if (!function_exists('oprc_extract_delivery_update_from_module')) {
    function oprc_extract_delivery_update_from_module($moduleId)
    {
      if (!is_string($moduleId) || $moduleId === '') {
        oprc_delivery_debug_log('Received invalid module identifier for delivery extraction', [
          'moduleId' => $moduleId,
          'type' => gettype($moduleId),
        ]);
        return '';
      }

      if (!isset($GLOBALS[$moduleId]) || !is_object($GLOBALS[$moduleId])) {
        $context = [
          'moduleId' => $moduleId,
          'shippingModulesState' => oprc_delivery_debug_collect_shipping_modules_state(),
        ];
        
        // Only collect globals if we have partial matches - avoid full iteration
        $globalsSummary = oprc_delivery_debug_collect_globals($moduleId);
        if (!empty($globalsSummary)) {
          $context['globalsSummary'] = $globalsSummary;
        }

        oprc_delivery_debug_log('Module globals entry missing while extracting delivery estimate', $context);
        return '';
      }

      $module = $GLOBALS[$moduleId];
      $moduleClass = get_class($module);
      $moduleProperties = array_keys(get_object_vars($module));
      $totalModuleProperties = count($moduleProperties);

      if ($totalModuleProperties > 10) {
        $moduleProperties = array_slice($moduleProperties, 0, 10);
        $moduleProperties[] = '...';
      }

      oprc_delivery_debug_log('Inspecting module for delivery estimate', [
        'moduleId' => $moduleId,
        'class' => $moduleClass,
        'hasDate' => property_exists($module, 'date'),
        'hasQuotes' => property_exists($module, 'quotes'),
        'hasGetDeliveryEstimate' => method_exists($module, 'getDeliveryEstimate'),
        'moduleProperties' => $moduleProperties,
      ]);

      if (property_exists($module, 'date')) {
        $moduleDate = $module->date;
        oprc_delivery_debug_log('Checking module->date for delivery estimate', [
          'moduleId' => $moduleId,
          'class' => $moduleClass,
          'rawType' => gettype($moduleDate),
          'rawValue' => oprc_delivery_debug_truncate($moduleDate),
        ]);

        if (is_string($moduleDate)) {
          $moduleDate = trim($moduleDate);
          if ($moduleDate !== '') {
            oprc_delivery_debug_log('Using module->date value for delivery estimate', [
              'moduleId' => $moduleId,
              'class' => $moduleClass,
              'value' => oprc_delivery_debug_truncate($moduleDate),
            ]);
            return $moduleDate;
          }
        }
      }

      $estimatedDateProperties = ['estimated_date', 'estimatedDate'];
      foreach ($estimatedDateProperties as $propertyName) {
        if (!property_exists($module, $propertyName)) {
          continue;
        }

        $moduleDate = $module->{$propertyName};
        oprc_delivery_debug_log('Checking module->{' . $propertyName . '} for delivery estimate', [
          'moduleId' => $moduleId,
          'class' => $moduleClass,
          'property' => $propertyName,
          'rawType' => gettype($moduleDate),
          'rawValue' => oprc_delivery_debug_truncate($moduleDate),
        ]);

        if (is_string($moduleDate)) {
          $moduleDate = trim($moduleDate);
          if ($moduleDate !== '') {
            oprc_delivery_debug_log('Using module->{' . $propertyName . '} value for delivery estimate', [
              'moduleId' => $moduleId,
              'class' => $moduleClass,
              'property' => $propertyName,
              'value' => oprc_delivery_debug_truncate($moduleDate),
            ]);
            return $moduleDate;
          }
        }
      }

      if (property_exists($module, 'quotes')) {
        $quotesValue = $module->quotes;
        $moduleDate = oprc_extract_delivery_update_from_quotes($quotesValue);
        if ($moduleDate !== '') {
          oprc_delivery_debug_log('Using module quotes for delivery estimate', [
            'moduleId' => $moduleId,
            'class' => $moduleClass,
            'value' => oprc_delivery_debug_truncate($moduleDate),
            'quotesSummary' => oprc_delivery_debug_summarize_value($quotesValue, 2),
          ]);
          return $moduleDate;
        }
        oprc_delivery_debug_log('Module quotes did not yield a delivery estimate', [
          'moduleId' => $moduleId,
          'class' => $moduleClass,
          'quotesType' => gettype($quotesValue),
          'quotesSummary' => oprc_delivery_debug_summarize_value($quotesValue, 2),
        ]);
      }

      if (method_exists($module, 'getDeliveryEstimate')) {
        try {
          $moduleDate = $module->getDeliveryEstimate();
        } catch (Exception $exception) {
          oprc_delivery_debug_log('Module getDeliveryEstimate() threw exception', [
            'moduleId' => $moduleId,
            'class' => $moduleClass,
            'exception' => get_class($exception),
            'message' => oprc_delivery_debug_truncate($exception->getMessage()),
          ]);
          $moduleDate = '';
        }

        if (is_string($moduleDate)) {
          $moduleDate = trim($moduleDate);
          if ($moduleDate !== '') {
            oprc_delivery_debug_log('Using module getDeliveryEstimate() value', [
              'moduleId' => $moduleId,
              'class' => $moduleClass,
              'value' => oprc_delivery_debug_truncate($moduleDate),
            ]);
            return $moduleDate;
          }
        }

        oprc_delivery_debug_log('Module getDeliveryEstimate() did not provide a usable value', [
          'moduleId' => $moduleId,
          'class' => $moduleClass,
          'rawType' => gettype($moduleDate),
          'rawValue' => oprc_delivery_debug_truncate($moduleDate),
          'shippingModulesState' => oprc_delivery_debug_collect_shipping_modules_state(),
        ]);
      }

      oprc_delivery_debug_log('Failed to extract delivery estimate from module', [
        'moduleId' => $moduleId,
        'class' => $moduleClass,
        'shippingModulesState' => oprc_delivery_debug_collect_shipping_modules_state(),
      ]);

      return '';
    }
  }

  if (!function_exists('oprc_extract_delivery_updates_from_quotes_list')) {
    function oprc_extract_delivery_updates_from_quotes_list($quotes)
    {
      $updates = [];

      if (!is_array($quotes)) {
        return $updates;
      }

      foreach ($quotes as $quoteEntry) {
        if (!is_array($quoteEntry) || !isset($quoteEntry['id'])) {
          continue;
        }

        $moduleId = $quoteEntry['id'];
        if (!is_string($moduleId) || $moduleId === '') {
          continue;
        }

        $moduleEstimate = oprc_extract_delivery_update_from_quotes($quoteEntry);
        if ($moduleEstimate === '' && isset($GLOBALS[$moduleId]) && is_object($GLOBALS[$moduleId])) {
          $moduleObject = $GLOBALS[$moduleId];

          if (property_exists($moduleObject, 'date')) {
            $moduleDate = $moduleObject->date;
            if (is_string($moduleDate)) {
              $moduleDate = trim($moduleDate);
              if ($moduleDate !== '') {
                $moduleEstimate = $moduleDate;
              }
            }
          }

          if ($moduleEstimate === '') {
            foreach (['estimated_date', 'estimatedDate'] as $propertyName) {
              if (!property_exists($moduleObject, $propertyName)) {
                continue;
              }

              $moduleDate = $moduleObject->{$propertyName};
              if (!is_string($moduleDate)) {
                continue;
              }

              $moduleDate = trim($moduleDate);
              if ($moduleDate === '') {
                continue;
              }

              $moduleEstimate = $moduleDate;
              break;
            }
          }
        }

        $methods = [];
        if (isset($quoteEntry['methods']) && is_array($quoteEntry['methods'])) {
          $methods = $quoteEntry['methods'];
        }

        $hasMethodEntries = false;

        foreach ($methods as $methodEntry) {
          if (!is_array($methodEntry) || !isset($methodEntry['id'])) {
            continue;
          }

          $methodId = $methodEntry['id'];
          if (!is_string($methodId) || $methodId === '') {
            continue;
          }

          $methodEstimate = oprc_extract_delivery_update_from_quotes($methodEntry);

          if ($methodEstimate === '' && $moduleEstimate !== '') {
            $methodEstimate = $moduleEstimate;
          }

          if ($methodEstimate === '') {
            continue;
          }

          $updates[$moduleId . '_' . $methodId] = $methodEstimate;
          $hasMethodEntries = true;
        }

        if (!$hasMethodEntries && $moduleEstimate !== '') {
          $updates[$moduleId] = $moduleEstimate;
        }
      }

      return $updates;
    }
  }

  if (!function_exists('oprc_remove_redundant_module_delivery_updates')) {
    function oprc_remove_redundant_module_delivery_updates(array $updates)
    {
      $hasMethodSpecific = [];

      foreach (array_keys($updates) as $key) {
        if (!is_string($key)) {
          continue;
        }

        $separatorPos = strpos($key, '_');
        if ($separatorPos === false) {
          continue;
        }

        $moduleId = substr($key, 0, $separatorPos);
        if ($moduleId !== '') {
          $hasMethodSpecific[$moduleId] = true;
        }
      }

      foreach (array_keys($updates) as $key) {
        if (!is_string($key) || strpos($key, '_') !== false) {
          continue;
        }

        if (isset($hasMethodSpecific[$key])) {
          unset($updates[$key]);
        }
      }

      return $updates;
    }
  }

  if (!function_exists('oprc_normalize_delivery_updates_array')) {
    function oprc_normalize_delivery_updates_array($updates)
    {
      if (!is_array($updates)) {
        return [];
      }

      $normalized = [];
      foreach ($updates as $moduleId => $value) {
        if (!is_string($moduleId) || $moduleId === '') {
          continue;
        }

        if (is_string($value)) {
          $value = trim($value);
        } elseif (is_array($value)) {
          $value = oprc_extract_delivery_update_from_quotes($value);
        } else {
          continue;
        }

        if ($value === '') {
          continue;
        }

        $normalized[$moduleId] = $value;
      }

      return $normalized;
    }
  }

  if (!function_exists('oprc_collect_delivery_updates')) {
    function oprc_collect_delivery_updates($shippingModules)
    {
      $deliveryUpdates = [];

      if (!isset($shippingModules) || !is_object($shippingModules)) {
        return $deliveryUpdates;
      }

      $quoteCollections = [];

      if (isset($shippingModules->quotes) && is_array($shippingModules->quotes)) {
        $quoteCollections[] = $shippingModules->quotes;
      }

      if (isset($GLOBALS['quotes']) && is_array($GLOBALS['quotes'])) {
        $quoteCollections[] = $GLOBALS['quotes'];
      }

      foreach ($quoteCollections as $quotes) {
        $extracted = oprc_extract_delivery_updates_from_quotes_list($quotes);
        if (!empty($extracted)) {
          $deliveryUpdates = array_merge($deliveryUpdates, $extracted);
        }
      }

      $moduleIdentifiers = [];

      if (method_exists($shippingModules, 'getInitializedModules')) {
        $initialized = $shippingModules->getInitializedModules();
        if (is_array($initialized)) {
          foreach ($initialized as $moduleClass) {
            if (!is_string($moduleClass) || $moduleClass === '') {
              continue;
            }

            if (strpos($moduleClass, '.') !== false) {
              $moduleClass = substr($moduleClass, 0, strrpos($moduleClass, '.'));
            }

            if ($moduleClass !== '') {
              $moduleIdentifiers[] = $moduleClass;
            }
          }
        }
      }

      if (isset($shippingModules->modules) && is_array($shippingModules->modules)) {
        foreach ($shippingModules->modules as $moduleEntry) {
          if (!is_string($moduleEntry) || $moduleEntry === '') {
            continue;
          }

          if (strpos($moduleEntry, '.') !== false) {
            $moduleEntry = substr($moduleEntry, 0, strrpos($moduleEntry, '.'));
          }

          if ($moduleEntry !== '') {
            $moduleIdentifiers[] = $moduleEntry;
          }
        }
      }

      $moduleIdentifiers = array_unique(array_filter($moduleIdentifiers));

      foreach ($moduleIdentifiers as $moduleId) {
        $moduleDate = oprc_extract_delivery_update_from_module($moduleId);
        if ($moduleDate === '') {
          continue;
        }

        $hasMethodSpecific = false;
        foreach ($deliveryUpdates as $key => $_value) {
          if (strpos($key, $moduleId . '_') === 0) {
            $hasMethodSpecific = true;
            break;
          }
        }

        if ($hasMethodSpecific) {
          continue;
        }

        if (!isset($deliveryUpdates[$moduleId])) {
          $deliveryUpdates[$moduleId] = $moduleDate;
        }
      }

      $fallbackUpdates = [];
      if (isset($GLOBALS['oprc_last_shipping_update'])) {
        $shippingUpdate = $GLOBALS['oprc_last_shipping_update'];
        if (is_array($shippingUpdate)) {
          if (isset($shippingUpdate['delivery_updates'])) {
            $fallbackUpdates = oprc_normalize_delivery_updates_array($shippingUpdate['delivery_updates']);
          }

          if (empty($fallbackUpdates) && isset($shippingUpdate['module_dates'])) {
            $fallbackUpdates = oprc_normalize_delivery_updates_array($shippingUpdate['module_dates']);
          }
        }
      }

      if (!empty($fallbackUpdates)) {
        foreach ($fallbackUpdates as $key => $value) {
          if (!isset($deliveryUpdates[$key])) {
            $deliveryUpdates[$key] = $value;
          }
        }
      }

      $deliveryUpdates = oprc_normalize_delivery_updates_array($deliveryUpdates);
      if (!empty($deliveryUpdates)) {
        $deliveryUpdates = oprc_remove_redundant_module_delivery_updates($deliveryUpdates);
      }

      return $deliveryUpdates;
    }
  }

  if (!function_exists('oprc_restore_module_dates')) {
    function oprc_restore_module_dates($shippingUpdate)
    {
      if (!is_array($shippingUpdate) || !isset($shippingUpdate['module_dates']) || !is_array($shippingUpdate['module_dates'])) {
        oprc_delivery_debug_log('oprc_restore_module_dates: No module dates to restore', [
          'hasShippingUpdate' => is_array($shippingUpdate),
          'hasModuleDates' => isset($shippingUpdate['module_dates']),
        ]);
        return;
      }

      $restored = [];
      $missing = [];
      
      foreach ($shippingUpdate['module_dates'] as $moduleId => $moduleDate) {
        if (!is_string($moduleId) || $moduleId === '') {
          continue;
        }

        if (!isset($GLOBALS[$moduleId]) || !is_object($GLOBALS[$moduleId])) {
          $missing[] = $moduleId;
          oprc_delivery_debug_log('oprc_restore_module_dates: Module object not found in GLOBALS', [
            'moduleId' => $moduleId,
            'moduleDate' => oprc_delivery_debug_truncate($moduleDate),
          ]);
          continue;
        }

        $GLOBALS[$moduleId]->date = $moduleDate;

        if (property_exists($GLOBALS[$moduleId], 'estimated_date')) {
          $GLOBALS[$moduleId]->estimated_date = $moduleDate;
        }

        if (property_exists($GLOBALS[$moduleId], 'estimatedDate')) {
          $GLOBALS[$moduleId]->estimatedDate = $moduleDate;
        }
        
        $restored[] = $moduleId;
      }
      
      if (!empty($restored) || !empty($missing)) {
        oprc_delivery_debug_log('oprc_restore_module_dates: Summary', [
          'restored' => $restored,
          'missing' => $missing,
        ]);
      }
    }
  }

  if (!function_exists('oprc_prepare_delivery_updates_for_quotes')) {
    function oprc_prepare_delivery_updates_for_quotes($quotes, $shippingModules = null, array $existingUpdates = [])
    {
      if (!is_array($quotes)) {
        $quotes = [];
      }

      // Ensure we have quotes even if the caller passed an empty list
      if (empty($quotes)) {
        if ($shippingModules !== null && isset($shippingModules->quotes) && is_array($shippingModules->quotes)) {
          $quotes = $shippingModules->quotes;
        } elseif (isset($GLOBALS['quotes']) && is_array($GLOBALS['quotes'])) {
          $quotes = $GLOBALS['quotes'];
        }
      }

      $normalizedExisting = oprc_normalize_delivery_updates_array($existingUpdates);

      oprc_delivery_debug_log('oprc_prepare_delivery_updates_for_quotes: Starting', [
        'quotesCount' => count($quotes),
        'hasShippingModules' => $shippingModules !== null,
        'existingUpdatesCount' => count($existingUpdates),
        'normalizedExistingCount' => count($normalizedExisting),
        'normalizedExisting' => $normalizedExisting,
      ]);

      if (empty($normalizedExisting) && $shippingModules !== null) {
        $normalizedExisting = oprc_collect_delivery_updates($shippingModules);
        oprc_delivery_debug_log('oprc_prepare_delivery_updates_for_quotes: Collected from shipping modules', [
          'collectedCount' => count($normalizedExisting),
          'collected' => $normalizedExisting,
        ]);
      }

      $moduleDates = [];
      $methodDates = [];
      $renderedUpdates = [];

      foreach ($quotes as $quoteEntry) {
        if (!is_array($quoteEntry) || !isset($quoteEntry['id'])) {
          continue;
        }

        $moduleId = $quoteEntry['id'];
        if (!is_string($moduleId) || $moduleId === '') {
          continue;
        }

        $methods = [];
        if (isset($quoteEntry['methods']) && is_array($quoteEntry['methods'])) {
          $methods = $quoteEntry['methods'];
        }

        // Add diagnostic logging to track method scanning
        oprc_delivery_debug_log('oprc_prepare_delivery_updates_for_quotes: quote methods scan', [
          'moduleId' => $moduleId,
          'methodsCount' => count($methods),
          'methodKeysPresent' => array_map(function($m){
            return array_keys(is_array($m) ? $m : []);
          }, array_slice($methods, 0, 3)),
          'firstMethodDate' => (isset($methods[0]['date']) ? $methods[0]['date'] : null),
        ]);

        if (!empty($methods)) {
          // Collect quote-level and module-level fallbacks (for methods without their own date)
          $quoteLevelDate = oprc_extract_delivery_update_from_quotes($quoteEntry);
          $moduleLevelDate = '';
          if ($quoteLevelDate === '') {
            $moduleLevelDate = oprc_extract_delivery_update_from_module($moduleId);
          }

          // Assign dates to each method
          foreach ($methods as $methodEntry) {
            if (!is_array($methodEntry) || !isset($methodEntry['id'])) {
              continue;
            }

            $methodId = $methodEntry['id'];
            if (!is_string($methodId) || $methodId === '') {
              continue;
            }

            $optionKey = $moduleId . '_' . $methodId;

            // 0) Check if we have a cached value for this specific method (from existingUpdates)
            $eta = $normalizedExisting[$optionKey] ?? '';

            // 1) method-level 'date' (preferred)
            if ($eta === '' && isset($methodEntry['date']) && is_string($methodEntry['date']) && $methodEntry['date'] !== '') {
              $eta = $methodEntry['date'];
            }

            // 2) quote-level 'date' (includes moduleDate, date, etc. from quote)
            if ($eta === '' && $quoteLevelDate !== '') {
              $eta = $quoteLevelDate;
            }

            // 3) Check for cached module-level date
            if ($eta === '' && isset($normalizedExisting[$moduleId])) {
              $eta = $normalizedExisting[$moduleId];
            }

            // 4) module-level 'date' (legacy via module->date)
            if ($eta === '' && $moduleLevelDate !== '') {
              $eta = $moduleLevelDate;
            }

            if ($eta !== '') {
              $renderedUpdates[$optionKey] = oprc_render_html_snippet($eta);
              $methodDates[$optionKey] = $eta;

              if (!isset($moduleDates[$moduleId])) {
                $moduleDates[$moduleId] = $eta;
              }
            }
          }
        } else {
          // No methods array, fall back to extracting from quote or module level
          $moduleEstimate = $normalizedExisting[$moduleId] ?? '';
          $source = '';
          if ($moduleEstimate === '') {
            $moduleEstimate = oprc_extract_delivery_update_from_quotes($quoteEntry);
            if ($moduleEstimate !== '') {
              $source = 'quote';
            }
          } else {
            $source = 'existing';
          }

          if ($moduleEstimate === '') {
            $moduleEstimate = oprc_extract_delivery_update_from_module($moduleId);
            if ($moduleEstimate !== '') {
              $source = 'module';
            }
          }

          if ($moduleEstimate !== '') {
            $moduleDates[$moduleId] = $moduleEstimate;
            $methodDates[$moduleId] = $moduleEstimate;
            $rendered = oprc_render_html_snippet($moduleEstimate);
            if ($rendered !== '') {
              $renderedUpdates[$moduleId] = $rendered;
            }
            oprc_delivery_debug_log('oprc_prepare_delivery_updates_for_quotes: Found module estimate', [
              'moduleId' => $moduleId,
              'estimate' => oprc_delivery_debug_truncate($moduleEstimate, 100),
              'source' => $source,
            ]);
          } else {
            oprc_delivery_debug_log('oprc_prepare_delivery_updates_for_quotes: No module estimate found', [
              'moduleId' => $moduleId,
            ]);
          }
        }
      }

      if (!empty($methodDates)) {
        $methodDates = oprc_remove_redundant_module_delivery_updates($methodDates);
      }

      oprc_delivery_debug_log('oprc_prepare_delivery_updates_for_quotes: Completed', [
        'moduleDatesCount' => count($moduleDates),
        'methodDatesCount' => count($methodDates),
        'renderedUpdatesCount' => count($renderedUpdates),
        'renderedUpdates' => $renderedUpdates,
      ]);

      return [
        'module_dates' => $moduleDates,
        'method_dates' => $methodDates,
        'rendered_updates' => $renderedUpdates,
      ];
    }
  }

  if (!function_exists('oprc_get_min_length')) {
    function oprc_get_min_length($constantName)
    {
      return defined($constantName) ? constant($constantName) : 0;
    }
  }

  function zen_oprc_get_country_list($name, $selected = '', $parameters = '') {
    global $db;

    $countries_array = [];
    static $default_labels = [];
    $default_label = '';

    if (isset($default_labels[$name])) {
      $default_label = $default_labels[$name];
    } elseif (function_exists('zen_get_country_list')) {
      $existing_list = zen_get_country_list($name, $selected, $parameters);
      if (preg_match('/<option[^>]*value="\s*">(.*?)<\/option>/i', $existing_list, $matches)) {
        $charset = defined('CHARSET') ? CHARSET : 'UTF-8';
        $default_label = html_entity_decode($matches[1], ENT_COMPAT, $charset);
      }
    }

    if ($default_label === '') {
      if (defined('PLEASE_SELECT_COUNTRY')) {
        $default_label = PLEASE_SELECT_COUNTRY;
      } elseif (defined('PLEASE_SELECT')) {
        $default_label = PLEASE_SELECT;
      } else {
        $default_label = 'Please Choose Your Country';
      }
    }

    $default_labels[$name] = $default_label;

    $countries_array[] = ['id' => '', 'text' => $default_label];

    $countries = $db->Execute("select countries_id, countries_name from " . TABLE_COUNTRIES . " order by countries_name");
    while (!$countries->EOF) {
      $countries_array[] = [
        'id' => $countries->fields['countries_id'],
        'text' => zen_oprc_normalize_encoding($countries->fields['countries_name'])
      ];
      $countries->MoveNext();
    }

    return zen_draw_pull_down_menu($name, $countries_array, $selected, $parameters);
  }

  function zen_oprc_js_zone_list_shipping($country, $form, $field) {
    global $db;
    $countries = $db->Execute("select distinct zone_country_id
                               from " . TABLE_ZONES . "
                               order by zone_country_id");
    $num_country = 1;
    $output_string = '';
    while (!$countries->EOF) {
      if ($num_country == 1) {
        $output_string .= '  if (' . $country . ' == "' . $countries->fields['zone_country_id'] . '") {' . "\n";
      } else {
        $output_string .= '  } else if (' . $country . ' == "' . $countries->fields['zone_country_id'] . '") {' . "\n";
      }

      $states = $db->Execute("select zone_name, zone_id
                              from " . TABLE_ZONES . "
                              where zone_country_id = '" . $countries->fields['zone_country_id'] . "'
                              order by zone_name");
      $num_state = 1;
      while (!$states->EOF) {
        if ($num_state == '1') $output_string .= '    ' . $form . '.' . $field . '.options[0] = new Option("' . PLEASE_SELECT . '", "");' . "\n";
        $output_string .= '    ' . $form . '.' . $field . '.options[' . $num_state . '] = new Option("' . $states->fields['zone_name'] . '", "' . $states->fields['zone_id'] . '");' . "\n";
        $num_state++;
        $states->MoveNext();
      }
      $num_country++;
      $countries->MoveNext();
      $output_string .= '    hideStateFieldShipping(' . $form . ');' . "\n" ;
    }
    $output_string .= '  } else {' . "\n" .
                      '    ' . $form . '.' . $field . '.options[0] = new Option("' . TYPE_BELOW . '", "");' . "\n" .
                      '    showStateFieldShipping(' . $form . ');' . "\n" .
                      '  }' . "\n";
    return $output_string;
  }
 
  function enable_shippingAddressCheckbox() {
    if ($_SESSION['cart']->get_content_type() == 'virtual') {
      return false;
    }
    if (OPRC_SHIPPING_ADDRESS != 'true') {
      return false;
    }
    return true;  
  }
  
  function enable_shippingAddress() {
    if (isset($_POST['shippingAddress']) && $_POST['shippingAddress'] == '1') { 
      return false;
    }
    if ($_SESSION['cart']->get_content_type() == 'virtual') {
      return false;
    }
    if (OPRC_SHIPPING_ADDRESS != 'true' || OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'true') {
      return false;
    }
    return true;  
  }

  if (!function_exists('zen_output_string_protected')) {
      function zen_output_string_protected($str) {
          return zen_db_prepare_input($str);
       }
  }   

/*
  * Validation that the user owns the address that they are tring to use
  * (sometimes problems with SESSION will cause the an invalid address_id)
  */
  
  function user_owns_address($address_book_id) {
    global $db;
    $check_address_query = "SELECT count(*) AS total
                            FROM " . TABLE_ADDRESS_BOOK . "
                            WHERE customers_id = :customersID
                            AND address_book_id = :addressBookID";
  
     $check_address_query = $db->bindVars($check_address_query, ':customersID', $_SESSION['customer_id'], 'integer');
     $check_address_query = $db->bindVars($check_address_query, ':addressBookID', $address_book_id, 'integer');
     $check_address = $db->Execute($check_address_query);
  
        if ($check_address->fields['total'] == '1') {
          return true;
        }
        else {
          return false;
        }
  }

  /**
   * Requeue message stack in SESSION for redirect
   * @param  array $messageStack
   * @return array $messageStack
   */
  function requeue_messageStack_for_redirect ( $messageStack ) {
    // rebuild messageStack
    if (sizeof($messageStack->messages) > 0) {
      $messageStackNew = new messageStack();
      for ($i=0, $n=sizeof($messageStack->messages); $i<$n; $i++) {
        if (strpos($messageStack->messages[$i]['params'], 'messageStackWarning') !== false) {
          $messageStack->messages[$i]['type'] = 'warning';
        }
        if (strpos($messageStack->messages[$i]['params'], 'messageStackSuccess') !== false) {
          $messageStack->messages[$i]['type'] = 'success';
        }
        if (strpos($messageStack->messages[$i]['params'], 'messageStackCaution') !== false) {
          $messageStack->messages[$i]['type'] = 'caution';
        }
        $messageStackNew->add_session($messageStack->messages[$i]['class'], strip_tags($messageStack->messages[$i]['text']), $messageStack->messages[$i]['type']);
      }
      $messageStack->reset();
      $messageStack = $messageStackNew;
    }
    return $messageStack;
  }

// eof