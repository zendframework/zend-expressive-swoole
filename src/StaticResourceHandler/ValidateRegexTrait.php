<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Swoole\StaticResourceHandler;

use Zend\Expressive\Swoole\Exception;

use function preg_match;
use function restore_error_handler;
use function set_error_handler;

trait ValidateRegexTrait
{
    private function isValidRegex(string $regex) : bool
    {
        set_error_handler(function ($errno) {
            return $errno === E_WARNING;
        });
        $isValid = preg_match($regex, '') !== false;
        restore_error_handler();
        return $isValid;
    }

    /**
     * @throws Exception\InvalidArgumentException if any regexp is invalid
     */
    private function validateRegexList(array $regexList, string $type) : void
    {
        foreach ($regexList as $regex) {
            if (! $this->isValidRegex($regex)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'The %s regex "%s" is invalid',
                    $type,
                    $regex
                ));
            }
        }
    }
}
