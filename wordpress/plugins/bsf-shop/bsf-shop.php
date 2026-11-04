<?php
/**
 * Plugin Name: BSF Shop Tools
 * Description: Order lookup, sales report and order export for the demo store.
 * Version:     1.4.0
 * Author:      Brainstorm Force (demo)
 *
 * Endpoints (front end):
 *   /?bsf=orders&email=customer42@example.com   Order lookup + "recent customers" widget
 *   /?bsf=report                                Sales report (calls a slow upstream API)
 *   /?bsf=export                                CSV export of recent orders
 *
 * NOTE FOR CANDIDATES: the PHP code here is not the thing to rewrite.
 * Treat it as a third-party plugin you can't change. Fix the platform around it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', 'bsf_shop_router' );

function bsf_shop_router() {
	if ( empty( $_GET['bsf'] ) ) {
		return;
	}

	switch ( sanitize_key( $_GET['bsf'] ) ) {
		case 'orders':
			bsf_shop_orders_page();
			break;
		case 'report':
			bsf_shop_report_page();
			break;
		case 'export':
			bsf_shop_export();
			break;
		default:
			return;
	}
	exit;
}

function bsf_shop_header( $title ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title>';
	echo '<style>body{font-family:system-ui,sans-serif;max-width:860px;margin:40px auto;padding:0 16px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:6px;text-align:left}</style>';
	echo '</head><body><h1>' . esc_html( $title ) . '</h1>';
}

/**
 * Order lookup. Runs one lookup for the requested customer, plus a
 * "recent customers" widget that looks up 5 more.
 */
function bsf_shop_orders_page() {
	global $wpdb;
	$table = $wpdb->prefix . 'bsf_orders';
	$start = microtime( true );

	$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : 'customer42@example.com';
	$widget_emails = array( 'customer7@example.com', 'customer99@example.com', 'customer1234@example.com', 'customer31337@example.com', 'customer2026@example.com' );

	bsf_shop_header( 'Order lookup' );

	foreach ( array_merge( array( $email ), $widget_emails ) as $i => $lookup ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_sku, total, status, created_at FROM {$table} WHERE customer_email = %s ORDER BY created_at DESC LIMIT 10",
				$lookup
			)
		);
		echo '<h2>' . ( 0 === $i ? 'Orders for ' : 'Recent customer: ' ) . esc_html( $lookup ) . '</h2>';
		echo '<table><tr><th>ID</th><th>SKU</th><th>Total</th><th>Status</th><th>Date</th></tr>';
		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				(int) $row->id,
				esc_html( $row->product_sku ),
				esc_html( $row->total ),
				esc_html( $row->status ),
				esc_html( $row->created_at )
			);
		}
		echo '</table>';
	}

	printf( '<p><small>Generated in %.2fs</small></p></body></html>', microtime( true ) - $start );
}

/**
 * Sales report. The upstream "analytics API" takes ~8 seconds to respond.
 */
function bsf_shop_report_page() {
	bsf_shop_header( 'Sales report' );
	sleep( 8 ); // Simulates waiting on a slow third-party analytics API.
	echo '<p>Revenue (last 30 days): $48,210.00</p><p>Orders: 1,204</p></body></html>';
}

/**
 * CSV export of recent orders. Loads the full result set into memory first
 * (as many real plugins do).
 */
function bsf_shop_export() {
	global $wpdb;
	$table = $wpdb->prefix . 'bsf_orders';

	$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 50000", ARRAY_A );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="orders.csv"' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'id', 'customer_email', 'product_sku', 'total', 'status', 'notes', 'created_at' ) );
	foreach ( $rows as $row ) {
		fputcsv( $out, $row );
	}
	fclose( $out );
}
