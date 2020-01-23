<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Stores valid email(s) as per configuration.
 *
 * Usage:
 *  $user->addField('email', ['Email']);
 *  $user->addField('email_mx_check', ['Email', 'dns_check'=>true]);
 *  *  $user->addField('email_with_name', ['Email', 'include_names'=>true]);
 *  $user->addField('emails', ['Email', 'allow_multiple'=>true, 'separator'=>[',',';']]);
 *
 * Various options can also be combined.
 */
class Email extends Field
{
    /**
     * @var bool Enable lookup for MX record for email addresses stored
     */
    public $dns_check = false;

    /**
     * @var bool Permit entry of multiple email addresses, separated with comma (and extra spaces)
     */
    public $allow_multiple = false;

    /**
     * @var bool Also allow entry of names in format "Romans <me@example.com>"
     */
    public $include_names = false;

    /**
     * @var array Array of allowed separators
     */
    public $separator = [','];

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function normalize($value)
    {
        // split value by any number of separator characters
        $emails = preg_split('/['.implode('', array_map('preg_quote', $this->separator)).']+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        if (!$this->allow_multiple && count($emails) > 1) {
            throw new ValidationException([$this->name => 'Only a single email can be entered']);
        }

        // now normalize each email
        $emails = array_map(function ($email) {
            $email = trim($email);

            if ($this->include_names) {
                $email = preg_replace('/^[^<]*<([^>]*)>/', '\1', $email);
            }

            // should actually run only domain trough idn_to_ascii(), but for validation purpose this way it's fine too
            $p = explode('@', $email);
            $user = $p[0] ?? null;
            $domain = $p[1] ?? null;
            if (!filter_var($user.'@'.$this->idn_to_ascii($domain), FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException([$this->name => 'Email format is invalid']);
            }

            if ($this->dns_check) {
                if (!checkdnsrr($this->idn_to_ascii($domain), 'MX')) {
                    throw new ValidationException([$this->name => 'Email domain does not exist']);
                }
            }

            return $email;
        }, $emails);

        return parent::normalize(implode(', ', $emails));
    }

    /**
     * Return array of seed properties of this Field object.
     *
     * @param array $properties Properties to return, by default will return all properties set.
     *
     * @return array
     */
    public function getSeed(array $properties = []): array
    {
        $seed = parent::getSeed($properties);

        // [key => default_value]
        $properties = $properties ?: [
            'dns_check'      => false,
            'allow_multiple' => false,
            'include_names'  => false,
            'separator'      => [','],
        ];

        foreach ($properties as $k=>$v) {
            if ($this->{$k} !== $v) {
                $seed[$k] = $this->{$k};
            }
        }

        return $seed;
    }

    /**
     * Return translated address.
     *
     * @param string $domain
     *
     * @return string
     */
    protected function idn_to_ascii(?string $domain): ?string
    {
        return idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
    }
}
