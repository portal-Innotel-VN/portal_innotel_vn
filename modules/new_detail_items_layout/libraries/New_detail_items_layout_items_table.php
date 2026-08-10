<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(APPPATH . 'libraries/App_items_table.php');

/**
 * Custom sales document items table for:
 * # | Ma hang | Mo ta | So luong | Gia | Thue | Tong
 */
class New_detail_items_layout_items_table extends App_items_table
{
    /**
     * @return string
     */
    public function items()
    {
        $html              = '';
        $customFieldsItems = $this->get_custom_fields_for_table();
        $itemNameWidth     = 20;
        $configWidth       = 25;
        $regularItemWidth  = $this->get_regular_items_width_for_split_layout($itemNameWidth, $configWidth, 5, count($customFieldsItems));
        $fontSizeStyle     = $this->get_font_size_style();

        $i = 1;
        foreach ($this->items as $item) {
            $itemHTML = '';

            $itemHTML .= '<tr' . $this->tr_attributes($item) . '>';
            $itemHTML .= '<td' . $this->td_attributes() . ' align="center" width="5%">' . $i . '</td>';

            $itemHTML .= '<td class="description" align="left" width="' . $itemNameWidth . '%">';
            if (!empty($item['description'])) {
                $itemHTML .= '<span' . $fontSizeStyle . '><strong>'
                    . $this->period_merge_field($item['description'])
                    . '</strong></span>';
            }
            $itemHTML .= '</td>';

            $itemHTML .= '<td class="configuration" align="left" width="' . $configWidth . '%">';
            if (!empty($item['long_description'])) {
                $itemHTML .= '<span style="color:#424242' . $this->get_inline_pdf_font_size() . ';">'
                    . $this->period_merge_field($item['long_description'])
                    . '</span>';
            }
            $itemHTML .= '</td>';

            foreach ($customFieldsItems as $custom_field) {
                $itemHTML .= '<td align="left" width="' . $regularItemWidth . '%">'
                    . get_custom_field_value($item['id'], $custom_field['id'], 'items')
                    . '</td>';
            }

            $itemHTML .= '<td align="right" width="' . $regularItemWidth . '%">' . floatVal($item['qty']);
            if ($item['unit']) {
                $itemHTML .= ' ' . $item['unit'];
            }
            $itemHTML .= '</td>';

            $rate = hooks()->apply_filters(
                'item_preview_rate',
                app_format_money($item['rate'], $this->transaction->currency_name, $this->exclude_currency()),
                ['item' => $item, 'transaction' => $this->transaction]
            );
            $itemHTML .= '<td align="right" width="' . $regularItemWidth . '%">' . $rate . '</td>';

            $itemHTML .= $this->detail_taxes_html($item, $regularItemWidth);

            $item_amount_with_quantity = hooks()->apply_filters(
                'item_preview_amount_with_currency',
                app_format_money(($item['qty'] * $item['rate']), $this->transaction->currency_name, $this->exclude_currency()),
                $item,
                $this->transaction,
                $this->exclude_currency()
            );

            $itemHTML .= '<td class="amount" align="right" width="' . $regularItemWidth . '%">' . $item_amount_with_quantity . '</td>';
            $itemHTML .= '</tr>';

            $html .= $itemHTML;
            $i++;
        }

        return $html;
    }

    /**
     * @return string
     */
    public function html_headings()
    {
        $customFieldsItems = $this->get_custom_fields_for_table();
        $itemNameWidth     = 20;
        $configWidth       = 25;

        $html  = '<tr>';
        $html .= '<th align="center">' . $this->number_heading() . '</th>';
        $html .= '<th class="description" width="' . $itemNameWidth . '%" align="left">' . $this->item_heading() . '</th>';
        $html .= '<th class="configuration" width="' . $configWidth . '%" align="left">Mô tả</th>';

        foreach ($customFieldsItems as $cf) {
            $html .= '<th class="custom_field" align="left">' . $cf['name'] . '</th>';
        }

        $html .= '<th align="right">' . $this->qty_heading() . '</th>';
        $html .= '<th align="right">' . $this->rate_heading() . '</th>';
        $html .= '<th align="right">' . $this->tax_heading() . '</th>';
        $html .= '<th align="right">' . $this->amount_heading() . '</th>';
        $html .= '</tr>';

        return $html;
    }

    /**
     * @return string
     */
    public function pdf_headings()
    {
        $customFieldsItems = $this->get_custom_fields_for_table();
        $itemNameWidth     = 18;
        $configWidth       = 22;
        $regularItemWidth  = $this->get_regular_items_width_for_split_layout($itemNameWidth, $configWidth, 5, count($customFieldsItems));

        $tblhtml  = '<tr height="30" bgcolor="' . get_option('pdf_table_heading_color') . '" style="color:' . get_option('pdf_table_heading_text_color') . ';">';
        $tblhtml .= '<th width="5%" align="center">' . $this->number_heading() . '</th>';
        $tblhtml .= '<th width="' . $itemNameWidth . '%" align="left">' . $this->item_heading() . '</th>';
        $tblhtml .= '<th width="' . $configWidth . '%" align="left">Mô tả</th>';

        foreach ($customFieldsItems as $cf) {
            $tblhtml .= '<th width="' . $regularItemWidth . '%" align="left">' . $cf['name'] . '</th>';
        }

        $tblhtml .= '<th width="' . $regularItemWidth . '%" align="right">' . $this->qty_heading() . '</th>';
        $tblhtml .= '<th width="' . $regularItemWidth . '%" align="right">' . $this->rate_heading() . '</th>';
        $tblhtml .= '<th width="' . $regularItemWidth . '%" align="right">' . $this->tax_heading() . '</th>';
        $tblhtml .= '<th width="' . $regularItemWidth . '%" align="right">' . $this->amount_heading() . '</th>';
        $tblhtml .= '</tr>';

        return $tblhtml;
    }

    /**
     * @param int $itemNameWidth
     * @param int $configWidth
     * @param int $numberWidth
     * @param int $customFieldsCount
     * @return float
     */
    private function get_regular_items_width_for_split_layout($itemNameWidth, $configWidth, $numberWidth, $customFieldsCount)
    {
        return (100 - $numberWidth - $itemNameWidth - $configWidth) / (4 + $customFieldsCount);
    }

    /**
     * Sales documents always show the tax column in this layout.
     *
     * @param array $item
     * @param float $width
     * @return string
     */
    private function detail_taxes_html($item, $width)
    {
        $itemHTML = '<td align="right" width="' . $width . '%">';

        if (count($item['taxes']) > 0) {
            foreach ($item['taxes'] as $tax) {
                $item_tax = '';
                if ((count($item['taxes']) > 1 && get_option('remove_tax_name_from_item_table') == false)
                    || get_option('remove_tax_name_from_item_table') == false
                    || multiple_taxes_found_for_item($item['taxes'])) {
                    $tmp      = explode('|', $tax['taxname']);
                    $item_tax = $tmp[0] . ' ' . app_format_number($tmp[1]) . '%<br />';
                } else {
                    $item_tax .= app_format_number($tax['taxrate']) . '%';
                }

                $itemHTML .= hooks()->apply_filters('item_tax_table_row', $item_tax, $item);
            }
        } else {
            $itemHTML .= hooks()->apply_filters('item_tax_table_row', '0%', $item);
        }

        $itemHTML .= '</td>';

        return $itemHTML;
    }

    /**
     * @return string
     */
    private function get_font_size_style()
    {
        $fontSize = $this->get_pdf_font_size();

        return $fontSize ? ' style="font-size:' . $fontSize . 'px;"' : '';
    }

    /**
     * @return string
     */
    private function get_inline_pdf_font_size()
    {
        $fontSize = $this->get_pdf_font_size();

        return $fontSize ? '; font-size:' . $fontSize . 'px' : '';
    }
}
