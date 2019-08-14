/**
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé G. - Twitter : @vrnet
 */

function submitProductSelectionForm(url) {
	$('edit_form').writeAttribute('action', url).submit();
}