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

use advanced_testcase;

/**
 * Tests source/hash cache behaviour.
 *
 * @covers \\local_contentfreshness\\cache_manager
 * @package local_contentfreshness
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cache_manager_test extends advanced_testcase {
    /**
     * Changed content hashes invalidate old AI results.
     */
    public function test_hash_change_invalidates_cached_ai_result(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cache = new cache_manager();

        $cache->store_local($course->id, 'page:10:content', str_repeat('a', 64), [
            'candidates' => [['candidateid' => 'local']],
            'urls' => ['https://example.org/'],
        ]);
        $cache->store_ai($course->id, 'page:10:content', str_repeat('a', 64), [
            ['candidateid' => 'abc', 'classification' => 'evergreen'],
        ]);

        $samehash = $cache->get($course->id, 'page:10:content', str_repeat('a', 64));
        $this->assertNotNull($samehash);
        $this->assertNotNull($samehash->localresultjson);
        $this->assertNull($cache->get($course->id, 'page:10:content', str_repeat('b', 64)));

        $cache->store_ai($course->id, 'page:10:content', str_repeat('b', 64), []);
        $record = $cache->get($course->id, 'page:10:content', str_repeat('b', 64));
        $this->assertNotNull($record);
        $this->assertSame('[]', $record->airesultjson);
        $this->assertNull($record->localresultjson);
        $this->assertNull($record->linkresultjson);
    }
}
