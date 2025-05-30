<?php

function get_saml_settings() {
    $opts = get_option('saml_settings', []);

    var_dump($opts); // Debugging line to inspect the options

    return [
        'strict' => true,
        'debug' => true,
        'sp' => [
            'entityId' => $opts['sp_entity_id'] ?? home_url("/"),
            'assertionConsumerService' => [
                'url' => $opts['sp_acs_url'] ?? home_url('?sso=1&acs=1'),
                'binding' => $opts['sp_entity_binding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            ],
            'x509cert' => '',
            'privateKey' => '',
        ],
        'idp' => [
            'entityId' => $opts['idp_entity_id'] ?? 'https://saml.example.com/entityid',
            'singleSignOnService' => [
                'url' => $opts['idp_sso_url'] ?? 'https://mocksaml.com/api/saml/sso',
                'binding' => $opts['idp_entity_binding'] ?? 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'x509cert' => $opts['idp_x509_cert'] ?? '',
        ],
    ];
}