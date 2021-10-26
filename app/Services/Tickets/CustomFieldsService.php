<?php

namespace FluentSupport\App\Services\Tickets;

class CustomFieldsService
{
    public function getCustomFields()
    {
        $customFields = [
            [
                'data_key' => 'ticket_type',
                'admin_label' => 'Support Type',
                'public_label' => 'Please let us know about this support type',
                'type' => 'input-select',
                'placeholder' => 'Support Type',
                'options' => [
                    'bug_report' => 'Bug Report',
                    'feature_request' => 'Feature Request',
                    'integration_issues' => 'Integration Issues'
                ],
                'required' => true
            ],
            [
                'data_key' => 'invoice_number',
                'admin_label' => 'Invoice Number',
                'public_label' => 'Please let us know your invoice number',
                'type' => 'input-text',
                'placeholder' => 'Invoice Number',
                'data-type' => 'text', // number / text
                'required' => false
            ],
            [
                'data_key' => 'details_about_issue',
                'admin_label' => 'Details about the issue',
                'public_label' => 'Please let us know more details about this issue',
                'type' => 'input-textarea',
                'placeholder' => 'Details about the issue',
                'required' => false
            ]
        ];
    }



}
