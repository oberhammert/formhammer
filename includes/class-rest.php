<?php

declare(strict_types=1);

final class Formhammer_REST
{
    private const NAMESPACE = 'formhammer/v1';
    private const TOKEN_ROUTE = '/token';

    public function __construct(private Formhammer_Validator $validator)
    {
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            self::TOKEN_ROUTE,
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_token_request'],
                'permission_callback' => '__return_true',
                'args' => [
                    'form_id' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ]
        );
    }

    public function handle_token_request(WP_REST_Request $request): array|WP_Error
    {
        $form_id = $request->get_param('form_id');

        if (!is_string($form_id) || trim($form_id) === '') {
            return new WP_Error(
                'formhammer_missing_form_id',
                'Missing form_id.',
                ['status' => 400]
            );
        }

        return [
            'token' => $this->validator->generate_token($form_id),
        ];
    }
}
