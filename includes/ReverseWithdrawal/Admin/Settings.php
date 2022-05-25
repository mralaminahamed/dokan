<?php
namespace WeDevs\Dokan\ReverseWithdrawal\Admin;

use WeDevs\Dokan\ReverseWithdrawal\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Settings
 *
 * @package WeDevs\Dokan\ReverseWithdrawal\Admin
 *
 * @since DOKAN_SINCE
 */
class Settings {

    /**
     * Settings constructor.
     *
     * @since DOKAN_SINCE
     */
    public function __construct() {
        // Hooks
        add_filter( 'dokan_settings_fields', [ $this, 'load_settings_fields' ], 21 );
        add_action( 'dokan_before_saving_settings', [ $this, 'validate_admin_settings' ], 20, 2 );
    }

    /**
     * Load all settings fields
     *
     * @since DOKAN_SINCE
     *
     * @param array $fields
     *
     * @return array
     */
    public function load_settings_fields( $fields ) {
        $settings_fields = [
            'enabled' => [
                'name'    => 'enabled',
                'label'   => esc_html__( 'Enable Reverse Withdrawal', 'dokan-lite' ),
                'desc'    => esc_html__( 'Check this checkbox if you want to enable reverse withdrawal feature for vendors.', 'dokan-lite' ),
                'type'    => 'checkbox',
                'default' => 'off',
                'refresh_after_save' => true,
            ],
            'payment_gateways' => [
                'name'    => 'payment_gateways',
                'label'   => esc_html__( 'Enable Reverse Withdrawal for this Gateway', 'dokan-lite' ),
                'desc'    => esc_html__( 'Check the payment gateways you want to enable reverse withdrawal for. For now, only cash on delivery is available.', 'dokan-lite' ),
                'type'    => 'multicheck',
                'options' => SettingsHelper::get_reverse_withrawal_payment_gateways(),
                'default' => [ 'cod' => 'cod' ],
            ],
            'billing_type' => [
                'name'    => 'billing_type',
                'label'   => esc_html__( 'Billing Type', 'dokan-lite' ),
                'desc'    => esc_html__( 'Select how vendors will be charged for their reverse balance.', 'dokan-lite' ),
                'type'    => 'select',
                'options' => SettingsHelper::get_billing_type_options(),
                'default' => 'by_amount',
            ],
            'reverse_balance_threshold' => [
                'name'    => 'reverse_balance_threshold',
                'label'   => sprintf( '%1$s (%2$s)', esc_html__( 'Reverse Balance Threshold', 'dokan-lite' ), get_woocommerce_currency() ),
                'desc'    => esc_html__( 'Set reverse withdrawal threshold limit.', 'dokan-lite' ),
                'type'    => 'number',
                'min'     => 0,
                'default' => '150',
                'step'    => '0.5',
                'show_if' => [
                    'billing_type' => [
                        'equal' => 'by_amount',
                    ],
                ],
            ],
            'monthly_billing_day' => [
                'name'    => 'monthly_billing_day',
                'label'   => esc_html__( 'Monthly Billing Date', 'dokan-lite' ),
                'desc'    => esc_html__( 'Enter the day of month when you want to send reverse withdrawal balance invoice to vendors.', 'dokan-lite' ),
                'type'    => 'number',
                'min'     => 1,
                'max'     => 28,
                'step'    => 1,
                'default' => '1',
                'show_if' => [
                    'billing_type' => [
                        'equal' => 'by_month',
                    ],
                ],
            ],
            'due_period' => [
                'name'    => 'due_period',
                'label'   => esc_html__( 'Grace Period', 'dokan-lite' ),
                'desc'    => esc_html__( 'Maximum Payment Due period in day(s) before selected action(s) is/are taken. Enter 0 to take action(s) immediately.', 'dokan-lite' ),
                'type'    => 'number',
                'min'     => 0,
                'max'     => 28,
                'step'    => 1,
                'default' => '7',
            ],
            'failed_actions' => [
                'name'    => 'failed_actions',
                'label'   => esc_html__( 'After Grace Period', 'dokan-lite' ),
                'desc'    => esc_html__( 'Select one or more actions to perform after due period is over and vendors was unable to pay.', 'dokan-lite' ),
                'type'    => 'multicheck',
                'options' => SettingsHelper::get_failed_payment_actions(),
                'default' => [ 'enable_catalog_mode' => 'enable_catalog_mode' ],
            ],
            'display_notice' => [
                'name'    => 'display_notice',
                'label'   => esc_html__( 'Display Notice During Grace Period', 'dokan-lite' ),
                'desc'    => esc_html__( 'Display notice to pay reverse withdrawal balance during grace period under vendor dashboard.', 'dokan-lite' ),
                'type'    => 'checkbox',
                'default' => 'on',
            ],
        ];

        // Dokan pro related settings
        if ( dokan()->is_pro_exists() ) {
            $settings_fields['send_announcement'] = [
                'name'    => 'send_announcement',
                'label'   => esc_html__( 'Send Announcement?', 'dokan-lite' ),
                'desc'    => esc_html__( 'Check this checkbox if you want to send an announcement during the grace period. Note that a maximum of one announcement will be sent during a single billing period.', 'dokan-lite' ),
                'type'    => 'checkbox',
                'default' => 'off',
            ];
        }

        $fields['dokan_reverse_withdrawal'] = apply_filters( 'dokan_reverse_withdrawal_setting_fields', $settings_fields );

        return $fields;
    }

    /**
     * Validates admin delivery settings
     *
     * @since DOKAN_SINCE
     *
     * @param string $option_name
     * @param array $option_value
     *
     * @return void
     */
    public function validate_admin_settings( $option_name, $option_value ) {
        if ( 'dokan_reverse_withdrawal' !== $option_name ) {
            return;
        }

        $billing_type = isset( $option_value['billing_type'] ) ? sanitize_text_field( $option_value['billing_type'] ) : 'by_amount';
        $reverse_balance_threshold = isset( $option_value['reverse_balance_threshold'] ) ? floatval( $option_value['reverse_balance_threshold'] ) : 150;
        $monthly_billing_day = isset( $option_value['monthly_billing_day'] ) ? intval( $option_value['monthly_billing_day'] ) : 1;
        $due_period = isset( $option_value['due_period'] ) ? intval( $option_value['due_period'] ) : 7;
        $failed_actions = isset( $option_value['failed_actions'] ) ? array_filter( $option_value['failed_actions'] ) : [];

        $errors = [];

        if ( $due_period < 0 || $due_period > 28 ) {
            $message = $due_period < 0 ? esc_html__( 'Due period cannot be negative.', 'dokan-lite' ) : esc_html__( 'Due period cannot be greater than 28.', 'dokan-lite' );
            $errors[] = [
                'name'  => 'due_period',
                'error' => $message,
            ];
        }

        if ( empty( $failed_actions ) ) {
            $errors[] = [
                'name'  => 'failed_actions',
                'error' => esc_html__( 'Please select at least one action.', 'dokan-lite' ),
            ];
        }

        if ( 'by_amount' === $billing_type ) {
            if ( $reverse_balance_threshold <= 0 ) {
                $errors[] = [
                    'name'  => 'reverse_balance_threshold',
                    'error' => esc_html__( 'Reverse balance threshold cannot be zero or negative.', 'dokan-lite' ),
                ];
            }
        } else {
            if ( $monthly_billing_day < 1 || $monthly_billing_day > 28 ) {
                $message = $monthly_billing_day < 1 ? esc_html__( 'Monthly billing day cannot be negative.', 'dokan-lite' ) : esc_html__( 'Monthly billing day cannot be greater than 28.', 'dokan-lite' );
                $errors[] = [
                    'name'  => 'monthly_billing_day',
                    'error' => $message,
                ];
            }

            // check if billing day + due period is greater than 28
            if ( $monthly_billing_day + $due_period > 28 ) {
                $errors[] = [
                    'name'  => 'monthly_billing_day',
                    'error' => esc_html__( 'Monthly billing day + due period cannot be greater than 28.', 'dokan-lite' ),
                ];
            }
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error(
                [
                    'settings' => [
                        'name'  => $option_name,
                        'value' => $option_value,
                    ],
                    'message'  => __( 'Validation error', 'dokan-lite' ),
                    'errors' => $errors,
                ],
                400
            );
        }
    }
}
