<?php

declare(strict_types=1);

namespace Hofff\Contao\LanguageRelations\Widget;

use Contao\StringUtil;
use Contao\Widget;

use function sprintf;

/**
 * Display a hidden field with a fixed value in the backend and the option name next to it.
 * This is useful when you need predefined values in a MultiColumnWizard i.e.
 *
 * Copyright: The widget was taken from discordier/justtextwidgets and licensed under the LGPL-3.0-or-later
 *
 * @See https://github.com/discordier/justtextwidgets/blob/master/src/Widgets/JustATextOption.php
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class TextOptionsWidget extends Widget
{
    /**
     * The name of the template.
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    protected $strTemplate = 'be_widget';

    /**
     * Add specific attributes.
     *
     * @param string $strKey   The name of the key to set.
     * @param mixed  $varValue The value to use.
     */
    public function __set($strKey, $varValue): void
    {
        if ($strKey === 'options') {
            $this->arrOptions = StringUtil::deserialize($varValue);

            return;
        }

        parent::__set($strKey, $varValue);
    }

    /**
     * Generate the widget and return it as string.
     */
    public function generate(): string
    {
        // Add empty option (XHTML) if there are none
        if (empty($this->arrOptions)) {
            $this->arrOptions = [
                [
                    'value' => '',
                    'label' => '-',
                ],
            ];
        }

        $strClass = ($this->strClass !== '' ? ' class="' . $this->strClass . '"' : '');
        $strStyle = (!empty($this->arrAttributes['style']) ? ' style="' . $this->arrAttributes['style'] . '"' : '');

        return $this->checkOptGroup($this->arrOptions, $strClass, $strStyle);
    }

    /**
     * Scan an option group for the selected option.
     *
     * @param array<array<string,mixed>> $options The option array.
     * @param string                     $class   The html class to use.
     * @param string                     $style   The html style to use.
     */
    private function checkOptGroup(array $options, string $class, string $style): string
    {
        foreach ($options as $option) {
            // If it is an option group, handle it.
            if (! isset($option['value'])) {
                $result = $this->checkOptGroup($option, $class, $style);
                if ($result) {
                    return $result;
                }

                continue;
            }

            // No option group, check if it is selected.
            if ($this->isSelected($option)) {
                return sprintf(
                    '<input type="hidden" id="ctrl_%s" name="%s" value="%s" /><span%s>%s</span>',
                    $this->strId,
                    $this->strName,
                    StringUtil::specialchars($option['value']),
                    $class . $style,
                    $option['label']
                );
            }
        }

        return '';
    }
}
