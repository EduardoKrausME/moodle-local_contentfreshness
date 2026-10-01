<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_contentfreshness;

use curl;

/**
 * Safe external URL checker.
 *
 * It performs only HTTP/HTTPS HEAD requests through Moodle's curl wrapper.
 * Moodle's curl security helper validates resolved IPs and redirects, which is
 * essential to avoid turning a course editor URL into an SSRF primitive.
 *
 * @package   local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link_checker {
    /**
     * Validate the URL shape before Moodle's network-layer security helper runs.
     *
     * @param string $url URL.
     * @return bool
     */
    public function validate_url(string $url): bool {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (!in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (isset($parts['port']) && !in_array((int)$parts['port'], [80, 443], true)) {
            return false;
        }

        $host = strtolower(rtrim((string)$parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') ||
            str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        // Be intentionally conservative with literal IPs. DNS hosts still pass
        // through Moodle's security helper, which validates their resolved IPs.
        $literalhost = trim($host, '[]');
        if (filter_var($literalhost, FILTER_VALIDATE_IP)) {
            return false;
        }

        return true;
    }

    /**
     * Return true only for a URL whose host differs from this Moodle site.
     *
     * @param string $url URL.
     * @return bool
     */
    public function is_external_url(string $url): bool {
        global $CFG;

        $targethost = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        $sitehost = strtolower((string)(parse_url($CFG->wwwroot, PHP_URL_HOST) ?? ''));
        return $targethost !== '' && ($sitehost === '' || $targethost !== $sitehost);
    }

    /**
     * Check one external link using Moodle's SSRF-aware curl wrapper.
     *
     * @param string $url URL.
     * @param int $timeout Timeout seconds.
     * @return array{url:string,status:string,httpcode:int,error:string}
     */
    public function check(string $url, int $timeout = 5): array {
        global $CFG;

        if (!$this->validate_url($url)) {
            return [
                'url' => $url,
                'status' => 'not_checked_unsafe',
                'httpcode' => 0,
                'error' => 'URL rejected before network access.',
            ];
        }

        require_once($CFG->libdir . '/filelib.php');

        $timeout = max(1, min(15, $timeout));
        $curl = new curl();
        $curl->head($url, [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => min(3, $timeout),
            'CURLOPT_MAXREDIRS' => 3,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ]);

        $httpcode = (int)($curl->info['http_code'] ?? 0);
        $error = trim((string)($curl->error ?? ''));
        $status = 'ok';

        $redirectcount = (int)($curl->info['redirect_count'] ?? 0);
        if ($httpcode >= 400 && $httpcode < 500) {
            $status = 'http_error';
        } else if ($httpcode >= 500) {
            $status = 'server_error';
        } else if ($httpcode >= 300 || $redirectcount > 0) {
            $status = 'redirect';
        } else if ($httpcode === 0 || $error !== '') {
            $status = 'blocked_or_unreachable';
        }

        return [
            'url' => $url,
            'status' => $status,
            'httpcode' => $httpcode,
            'error' => $error,
        ];
    }
}
