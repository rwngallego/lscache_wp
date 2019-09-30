<?php
/**
 * The import/export class.
 *
 * @since      	1.8.2
 * @package    	LiteSpeed_Cache
 * @subpackage 	LiteSpeed_Cache/inc
 * @author     	LiteSpeed Technologies <info@litespeedtech.com>
 */
namespace LiteSpeed ;

defined( 'WPINC' ) || exit ;

class Import extends Conf
{
	protected static $_instance ;
	const DB_PREFIX = 'import' ; // DB record prefix name

	private $__cfg ;

	const TYPE_IMPORT = 'import' ;
	const TYPE_EXPORT = 'export' ;
	const TYPE_RESET = 'reset' ;

	/**
	 * Init
	 *
	 * @since  1.8.2
	 * @access protected
	 */
	protected function __construct()
	{
		Log::debug( 'Import init' ) ;

		$this->__cfg = Config::get_instance() ;
	}

	/**
	 * Show summary of history
	 *
	 * @since  3.0
	 * @access public
	 */
	public function summary()
	{
		$log = self::get_option( 'import', array() ) ;

		return $log ;
	}

	/**
	 * Export settings
	 *
	 * @since  2.4.1
	 * @return string All settings data
	 */
	public function export()
	{
		return $this->_export( true ) ;
	}

	/**
	 * Export settings to file
	 *
	 * @since  1.8.2
	 * @access private
	 */
	private function _export( $only_data_return = false )
	{

		$data = $this->__cfg->get_options() ;

		$data = base64_encode( json_encode( $data ) ) ;

		if ( $only_data_return ) {
			return $data ;
		}

		$filename = $this->_generate_filename() ;

		// Update log
		$log = $this->summary() ;
		if ( empty( $log[ 'export' ] ) ) {
			$log[ 'export' ] = array() ;
		}
		$log[ 'export' ][ 'file' ] = $filename ;
		$log[ 'export' ][ 'time' ] = time() ;

		self::update_option( 'import', $log ) ;

		Log::debug( 'Import: Saved to ' . $filename ) ;

		@header( 'Content-Disposition: attachment; filename=' . $filename ) ;
		echo $data ;

		exit ;
	}

	/**
	 * Import settings
	 *
	 * @since  2.4.1
	 */
	public function import( $file )
	{
		return $this->_import( $file ) ;
	}

	/**
	 * Import settings from file
	 *
	 * @since  1.8.2
	 * @access private
	 */
	private function _import( $file = false )
	{
		if ( ! $file ) {
			if ( empty( $_FILES[ 'ls_file' ][ 'name' ] ) || substr( $_FILES[ 'ls_file' ][ 'name' ], -5 ) != '.data' || empty( $_FILES[ 'ls_file' ][ 'tmp_name' ] ) ) {
				Log::debug( 'Import: Failed to import, wront ls_file' ) ;

				$msg = __( 'Import failed due to file error.', 'litespeed-cache' ) ;
				Admin_Display::error( $msg ) ;

				return false ;
			}

			// Update log
			$log = $this->summary() ;
			if ( empty( $log[ 'import' ] ) ) {
				$log[ 'import' ] = array() ;
			}
			$log[ 'import' ][ 'file' ] = $_FILES[ 'ls_file' ][ 'name' ] ;
			$log[ 'import' ][ 'time' ] = time() ;

			self::update_option( 'import', $log ) ;

			$data = file_get_contents( $_FILES[ 'ls_file' ][ 'tmp_name' ] ) ;
		}
		else {
			$data = file_get_contents( $file ) ;
		}

		try {
			$data = json_decode( base64_decode( $data ), true ) ;
		} catch ( \Exception $ex ) {
			Log::debug( 'Import: Failed to parse serialized data' ) ;
			return false ;
		}

		if ( ! $data ) {
			Log::debug( 'Import: Failed to import, no data' ) ;
			return false ;
		}

		$this->__cfg->update_confs( $data ) ;


		if ( ! $file ) {
			Log::debug( 'Import: Imported ' . $_FILES[ 'ls_file' ][ 'name' ] ) ;

			$msg = sprintf( __( 'Imported setting file %s successfully.', 'litespeed-cache' ), $_FILES[ 'ls_file' ][ 'name' ] ) ;
			Admin_Display::succeed( $msg ) ;
		}
		else {
			Log::debug( 'Import: Imported ' . $file ) ;
		}

		return true ;

	}

	/**
	 * Reset all configs to default values.
	 *
	 * @since  2.6.3
	 * @access private
	 */
	private function _reset()
	{
		$options = $this->__cfg->load_default_vals() ;

		$this->__cfg->update_confs( $options ) ;

		Log::debug( '[Import] Reset successfully.' ) ;

		$msg = __( 'Reset successfully.', 'litespeed-cache' ) ;
		Admin_Display::succeed( $msg ) ;

	}

	/**
	 * Generate the filename to export
	 *
	 * @since  1.8.2
	 * @access private
	 */
	private function _generate_filename()
	{
		// Generate filename
		$parsed_home = parse_url( get_home_url() ) ;
		$filename = 'LSCWP_cfg-' ;
		if ( ! empty( $parsed_home[ 'host' ] ) ) {
			$filename .= $parsed_home[ 'host' ] . '_' ;
		}

		if ( ! empty( $parsed_home[ 'path' ] ) ) {
			$filename .= $parsed_home[ 'path' ] . '_' ;
		}

		$filename = str_replace( '/', '_', $filename ) ;

		$filename .= '-' . date( 'Ymd_His' ) . '.data' ;

		return $filename ;
	}

	/**
	 * Handle all request actions from main cls
	 *
	 * @since  1.8.2
	 * @access public
	 */
	public static function handler()
	{
		$instance = self::get_instance() ;

		$type = Router::verify_type() ;

		switch ( $type ) {
			case self::TYPE_IMPORT :
				$instance->_import() ;
				break ;

			case self::TYPE_EXPORT :
				$instance->_export() ;
				break ;

			case self::TYPE_RESET :
				$instance->_reset() ;
				break ;

			default:
				break ;
		}

		Admin::redirect() ;
	}

}