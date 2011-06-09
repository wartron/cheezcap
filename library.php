<?php
//
// CheezCap - Cheezburger Custom Administration Panel
// (c) 2008 - 2011 Cheezburger Network (Pet Holdings, Inc.)
// LOL: http://cheezburger.com
// Source: http://github.com/cheezburger/cheezcap/
// Authors: Kyall Barrows, Toby McKes, Stefan Rusek, Scott Porad
// License: GNU General Public License, version 2 (GPL), http://www.gnu.org/licenses/gpl-2.0.html
//

class CheezCapGroup {
	var $name;
	var $id;
	var $options;

	function __construct( $_name, $_id, $_options ) {
		$this->name = $_name;
		$this->id = "cap_$_id";
		$this->options = $_options;
	}

	function WriteHtml() {
		?>
		<table class="form-table" width="100%">
			<tr valign="top">
				<th scope="row">Option</th>
				<th scope="row">Value</th>
			</tr>
		<?php
			for ( $i=0; $i < count( $this->options ); $i++ ) {
				$this->options[$i]->WriteHtml();
			}
		?>
		</table>
		<?php
	}
}

class CheezCapOption {
	var $name;
	var $desc;
	var $id;
	var $_key;
	var $std;

	function __construct( $_name, $_desc, $_id, $_std ) {
		$this->name = $_name;
		$this->desc = $_desc;
		$this->id = "cap_$_id";
		$this->_key = $_id;
		$this->std = $_std;
	}

	function WriteHtml() {
		echo '';
	}

	function Update( $ignored ) {
		$value = stripslashes_deep( $_POST[$this->id] );
		update_option( $this->id, $value );
	}

	function Reset( $ignored ) {
		update_option( $this->id, $this->std );
	}

	function Import( $data ) {
		if ( array_key_exists( $this->id, $data->dict ) )
			update_option( $this->id, $data->dict[$this->id] );
	}

	function Export( $data ) {
		$data->dict[$this->id] = get_option( $this->id );
	}

	function get() {
		return get_option( $this->id );
	}
}

class CheezCapTextOption extends CheezCapOption {
	var $useTextArea;

	function __construct( $_name, $_desc, $_id, $_std = '', $_useTextArea = false ) {
		parent::__construct( $_name, $_desc, $_id, $_std );
		$this->useTextArea = $_useTextArea;
	}

	function WriteHtml() {
		$stdText = $this->std;

		$stdTextOption = get_option( $this->id );
	        if ( ! empty( $stdTextOption ) )
			$stdText = $stdTextOption;

		?>
		<tr valign="top">
			<th scope="row"><?php echo esc_html( $this->name . ':' ); ?></th>
		<?php
		$commentWidth = 2;
		if ( $this->useTextArea ) :
			$commentWidth = 1;
		?>
			<td rowspan="2"><textarea style="width:100%;height:100%;" name="<?php echo esc_attr( $this->id ); ?>" id="<?php echo esc_attr( $this->id ); ?>"><?php echo esc_textarea( $stdText ); ?></textarea>
		<?php
		else :
		?>
			<td><input name="<?php echo esc_attr( $this->id ); ?>" id="<?php echo esc_attr( $this->id ); ?>" type="text" value="<?php echo esc_attr( $stdText ); ?>" size="40" />
		<?php
		endif;
		?>
			</td>
		</tr>
                <tr valign="top"><td colspan="<?php echo absint( $commentWidth ); ?>"><small><?php echo esc_html( $this->desc ); ?></small></td></tr><tr valign="top"><td colspan="2"><hr /></td></tr>
		<?php
	}

	function get() {
		$value = get_option( $this->id );
		if ( empty( $value ) )
			return $this->std;
		return $value;
	}
}

class CheezCapDropdownOption extends CheezCapOption {
	var $options;

	function __construct( $_name, $_desc, $_id, $_options, $_stdIndex = 0 ) {
		parent::__construct( $_name, $_desc, $_id, $_stdIndex );
		$this->options = $_options;
	}

	function WriteHtml() {
		?>
		<tr valign="top">
			<th scope="row"><?php echo esc_html( $this->name ); ?></th>
			<td>
				<select name="<?php echo esc_attr( $this->id ); ?>" id="<?php echo esc_attr( $this->id ); ?>">
		<?php
		foreach( $this->options as $option ) :
		?>
					<option<?php if ( get_option( $this->id ) == $option || ( ! get_option( $this->id ) && $this->options[$this->std] == $option ) ) { echo ' selected="selected"'; } ?>><?php echo esc_html( $option ); ?></option>
		<?php
		endforeach;
		?>
				</select>
			</td>
		</tr>
		<tr valign="top">
			<td colspan=2>
				<small><?php echo esc_html( $this->desc ); ?></small><hr />
			</td>
		</tr>
		<?php
	}

	function get() {
		$value = get_option( $this->id, $this->std );
        	if ( strtolower( $value ) == 'disabled' )
			return false;
		return $value;
	}
}

class CheezCapBooleanOption extends CheezCapDropdownOption {
	var $default;

	function __construct( $_name, $_desc, $_id, $_default = false ) {
		$this->default = $_default;
		parent::__construct( $_name, $_desc, $_id, array( 'Disabled', 'Enabled' ), $_default ? 1 : 0 );
	}

	function get() {
		$value = get_option( $this->id, $this->default );
		if ( is_bool( $value ) )
			return $value;
		switch ( strtolower( $value ) ) {
			case 'true':
			case 'enable':
			case 'enabled':
				return true;
			default:
				return false;
		}
	}
}

// This class is the handy short cut for accessing config options
//
// $cap->post_ratings is the same as get_bool_option("cap_post_ratings", false)
//
class CheezCap {
	private $data = false;
	private $cache = array();

	function init() {
		if ( $this->data )
			return;

		$this->data = array();
		$options = cap_get_options();

		foreach ( $options as $group ) {
			foreach( $group->options as $option ) {
				$this->data[$option->_key] = $option;
			}
		}
	}

	public function __get( $name ) {
		$this->init();

		if ( array_key_exists( $name, $this->cache ) )
			return $this->cache[$name];

		$option = $this->data[$name];
		if ( empty( $option ) )
			throw new Exception( "Unknown key: $name" );

		$value = $this->cache[$name] = $option->get();
		return $value;
	}
}

class CheezCapImportData {
	var $dict = array();
}

function cap_serialize_export( $data ) {
	header( 'Content-disposition: attachment; filename=theme-export.txt' );
	echo serialize( $data );
	exit();
}