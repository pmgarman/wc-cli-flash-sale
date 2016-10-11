<?php
/*
 * Plugin Name: WooCommerce - CLI Flash Sale
 * Description: Global flash sale for *all* products in the catalog. This is a destructive action that impacts all products.
 * Plugin URI: http://garman.io/
 * Author: Garman.IO
 * Author URI: http://garman.io/
 * Version: 1.0.0
 * Domain Path: /languages
 *
 * Copyright (c) 2016 Garman.IO / Patrick Garman
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Check if WP_CLI exists, and only extend it if it does
if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'CP_CLI' ) ) {

    /**
     * Class CP_CLI
     */
    class WC_CLI_Flash_Sale extends WP_CLI_Command {

        public function enable( $args, $assoc_args ) {
            global $wpdb;

            $rate = isset( $assoc_args['sale'] ) ? absint( $assoc_args['sale'] ) : 20;

            $sale = absint( $rate ) / 100;

            // Modified SQL from wc_get_product_ids_on_sale(), but we don't want cached data
            $products = $wpdb->get_col( "
                SELECT post.ID FROM `$wpdb->posts` AS post
                WHERE post.post_type IN ( 'product', 'product_variation' )
                    AND post.post_status = 'publish'
                GROUP BY post.ID;
            " );

            $progress = \WP_CLI\Utils\make_progress_bar( 'Discounting Products', count( $products ) );

            foreach( $products as $product_id ) {
                $progress->tick();

                $product = wc_get_product( $product_id );

                if( false === $product ) {
                    continue;
                }

                $regular_price = $product->get_regular_price();
                $sale_price = $regular_price - ( $sale * $regular_price );

                update_post_meta( $product_id, '_price', $sale_price );
                update_post_meta( $product_id, '_sale_price', $sale_price );
            }

            $progress->finish();
        }

        public function disable() {
            global $wpdb;

            // SQL from wc_get_product_ids_on_sale(), but we don't want cached data
            $products = $wpdb->get_col( "
                SELECT post.ID FROM `$wpdb->posts` AS post
                LEFT JOIN `$wpdb->postmeta` AS meta ON post.ID = meta.post_id
                LEFT JOIN `$wpdb->postmeta` AS meta2 ON post.ID = meta2.post_id
                WHERE post.post_type IN ( 'product', 'product_variation' )
                    AND post.post_status = 'publish'
                    AND meta.meta_key = '_sale_price'
                    AND meta2.meta_key = '_price'
                    AND CAST( meta.meta_value AS DECIMAL ) >= 0
                    AND CAST( meta.meta_value AS CHAR ) != ''
                    AND CAST( meta.meta_value AS DECIMAL ) = CAST( meta2.meta_value AS DECIMAL )
                GROUP BY post.ID;
            " );

            $progress = \WP_CLI\Utils\make_progress_bar( 'Discounting Products', count( $products ) );

            foreach( $products as $product_id ) {
                $progress->tick();

                $product = wc_get_product( $product_id );

                if( false === $product ) {
                    continue;
                }

                $regular_price = $product->get_regular_price();

                update_post_meta( $product_id, '_price', $regular_price );
                update_post_meta( $product_id, '_sale_price', '' );
            }

            $progress->finish();
        }

    }

    WP_CLI::add_command( 'wc-flash-sale', 'WC_CLI_Flash_Sale' );
}