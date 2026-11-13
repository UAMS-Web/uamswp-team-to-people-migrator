<?php

/**
 * A minimal WP_Error for the Unit suite, which never loads WordPress.
 *
 * Only the parts the plugin and Brain Monkey's is_wp_error() use exist here, with
 * the public `errors` and `error_data` properties of the real class, so code that
 * mistakes an error for a list of terms fails the way it would under WordPress.
 */

declare(strict_types=1);

class WP_Error
{
    /**
     * @var array<string|int, array<int, string>>
     */
    public array $errors = [];

    /**
     * @var array<string|int, mixed>
     */
    public array $error_data = [];

    public function __construct(string|int $code = '', string $message = '')
    {
        if ($code !== '') {
            $this->errors[$code][] = $message;
        }
    }

    public function get_error_code(): string|int
    {
        return array_key_first($this->errors) ?? '';
    }

    public function get_error_message(): string
    {
        return $this->errors[$this->get_error_code()][0] ?? '';
    }
}
