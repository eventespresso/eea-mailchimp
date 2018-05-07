<?php
/**
 * Class EE_Div_Per_Section_Layout
 *
 * Spaced layout (mainly for list interests)
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 * @since           2.4.0.rc.000
 *
 */
class EE_Div_Per_Section_Spaced_Layout extends EE_Form_Section_Layout_Base
{

    /**
     *
     * @return string
     */
    public function layout_form_begin()
    {
        return EEH_HTML::nl(1);
    }


    /**
     * Lays out the row for the input, including label and errors.
     *
     * @param EE_Form_Input_Base $input
     * @return string
     */
    public function layout_input($input)
    {
        if ($input->get_display_strategy() instanceof EE_Text_Area_Display_Strategy ||
            $input->get_display_strategy() instanceof EE_Text_Input_Display_Strategy ||
            $input->get_display_strategy() instanceof EE_Admin_File_Uploader_Display_Strategy
        ) {
            $input->set_html_class($input->html_class() . ' large-text');
        }

        $html = '';
        if ($input instanceof EE_Hidden_Input) {
            $html .= EEH_HTML::nl() . $input->get_html_for_input();
        } elseif ($input instanceof EE_Submit_Input) {
            $html .= EEH_HTML::div($input->get_html_for_input(), $input->html_id() . '-submit-dv', $input->html_class() . '-submit-dv');
        } else {
            $html .= EEH_HTML::p(
                EEH_HTML::strong(EEH_HTML::nl() . $input->get_html_for_label()) .
                EEH_HTML::nl() . $input->get_html_for_errors() .
                EEH_HTML::nl() . EEH_HTML::br() . $input->get_html_for_input(),
                $input->html_id() . 'input-div',
                $input->html_class() . 'input-div'
            );
        }
        return $html;
    }


    /**
     * Lays out a row for the subsection.
     *
     * @param EE_Form_Section_Proper $form_section
     * @return string
     */
    public function layout_subsection($form_section)
    {
        return EEH_HTML::nl(1) . $form_section->get_html() . EEH_HTML::nl(-1);
    }


    /**
     *
     * @return string
     */
    public function layout_form_end()
    {
        return EEH_HTML::nl();
    }
}
