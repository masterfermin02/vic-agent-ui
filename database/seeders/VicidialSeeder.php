<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VicidialSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCampaign();
        $this->seedPhone();
        $this->seedAgentUser();
        $this->seedDispositions();
        $this->createVicidialListTable();
        $this->seedLeadList();
        $this->seedLeads();
        $this->updateLocalUserCredentials();
    }

    private function seedCampaign(): void
    {
        DB::connection('vicidial')->table('vicidial_campaigns')->insertOrIgnore([
            'campaign_id' => 'TESTCAMP',
            'campaign_name' => 'Test Campaign',
            'active' => 'Y',
            'dial_method' => 'MANUAL',
            'hopper_level' => 5,
            'auto_dial_level' => 1.00,
            'next_agent_call' => 'oldest_call_start',
            'local_call_time' => '9am-9pm',
            'voicemail_ext' => 'vm1',
            'campaign_allow_inbound' => 'N',
            'available_only_ratio_tally' => 0,
            'adaptive_dropped_percentage' => 0.00,
            'adaptive_maximum_level' => 1,
            'adaptive_intensity' => 0,
            'adaptive_dl_diff_target' => 0,
            'queue_priority' => 0,
        ]);
    }

    private function seedPhone(): void
    {
        DB::connection('vicidial')->table('phones')->insertOrIgnore([
            'extension' => '1001',
            'dialplan_number' => '1001',
            'voicemail_id' => 'default',
            'phone_ip' => '127.0.0.1',
            'computer_ip' => '127.0.0.1',
            'server_ip' => env('VICIDIAL_SERVER_IP', '127.0.0.1'),
            'login' => 'agent1',
            'pass' => '1234',
            'status' => 'Active',
            'active' => 'Y',
            'phone_context' => 'default',
            'phone_ring_timeout' => 60,
            'template_id' => '',
        ]);
    }

    private function seedAgentUser(): void
    {
        DB::connection('vicidial')->table('vicidial_users')->insertOrIgnore([
            'user' => 'agent1',
            'pass' => 'Test123!',
            'full_name' => 'Test Agent',
            'user_level' => 1,
            'active' => 'Y',
            'phone_login' => '1001',
            'phone_pass' => '1234',
            'hotkeys_active' => 0,
            'territory' => '',
        ]);
    }

    private function seedDispositions(): void
    {
        $db = DB::connection('vicidial');

        $dispositions = [
            ['status' => 'A', 'status_name' => 'Answered', 'selectable' => 'Y', 'sale' => 'N', 'dnc' => 'N'],
            ['status' => 'N', 'status_name' => 'No Answer', 'selectable' => 'Y', 'sale' => 'N', 'dnc' => 'N'],
            ['status' => 'B', 'status_name' => 'Busy', 'selectable' => 'Y', 'sale' => 'N', 'dnc' => 'N'],
            ['status' => 'NA', 'status_name' => 'Not Available', 'selectable' => 'Y', 'sale' => 'N', 'dnc' => 'N'],
            ['status' => 'NI', 'status_name' => 'Not Interested', 'selectable' => 'Y', 'sale' => 'N', 'dnc' => 'N'],
            ['status' => 'SALE', 'status_name' => 'Sale', 'selectable' => 'Y', 'sale' => 'Y', 'dnc' => 'N'],
            ['status' => 'DNC', 'status_name' => 'Do Not Call', 'selectable' => 'Y', 'sale' => 'N', 'dnc' => 'Y'],
            ['status' => 'DC', 'status_name' => 'Disconnected', 'selectable' => 'Y', 'sale' => 'N', 'dnc' => 'N'],
        ];

        foreach ($dispositions as $disposition) {
            $exists = $db->table('vicidial_campaign_statuses')
                ->where('campaign_id', 'TESTCAMP')
                ->where('status', $disposition['status'])
                ->exists();

            if (! $exists) {
                $db->table('vicidial_campaign_statuses')->insert([
                    'campaign_id' => 'TESTCAMP',
                    'human_answered' => 'N',
                    ...$disposition,
                ]);
            }
        }
    }

    /**
     * Create the vicidial_list table in the VICIdial database if it does not exist.
     * Schema sourced from extras/MySQL_AST_CREATE_tables.sql.
     */
    private function createVicidialListTable(): void
    {
        DB::connection('vicidial')->statement('
            CREATE TABLE IF NOT EXISTS vicidial_list (
                lead_id INT(9) UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
                entry_date DATETIME,
                modify_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                status VARCHAR(6),
                user VARCHAR(20),
                vendor_lead_code VARCHAR(20),
                source_id VARCHAR(50),
                list_id BIGINT(14) UNSIGNED NOT NULL DEFAULT 0,
                gmt_offset_now DECIMAL(4,2) DEFAULT 0.00,
                called_since_last_reset ENUM(\'Y\',\'N\',\'Y1\',\'Y2\',\'Y3\',\'Y4\',\'Y5\',\'Y6\',\'Y7\',\'Y8\',\'Y9\',\'Y10\',\'D\') DEFAULT \'N\',
                phone_code VARCHAR(10),
                phone_number VARCHAR(18) NOT NULL,
                title VARCHAR(4),
                first_name VARCHAR(30),
                middle_initial VARCHAR(1),
                last_name VARCHAR(30),
                address1 VARCHAR(100),
                address2 VARCHAR(100),
                address3 VARCHAR(100),
                city VARCHAR(50),
                state VARCHAR(2),
                province VARCHAR(50),
                postal_code VARCHAR(10),
                country_code VARCHAR(3),
                gender ENUM(\'M\',\'F\',\'U\') DEFAULT \'U\',
                date_of_birth DATE,
                alt_phone VARCHAR(12),
                email VARCHAR(70),
                security_phrase VARCHAR(100),
                comments VARCHAR(255),
                called_count SMALLINT(5) UNSIGNED DEFAULT 0,
                last_local_call_time DATETIME,
                `rank` SMALLINT(5) NOT NULL DEFAULT 0,
                owner VARCHAR(20) DEFAULT \'\',
                entry_list_id BIGINT(14) UNSIGNED NOT NULL DEFAULT 0,
                INDEX (phone_number),
                INDEX (list_id),
                INDEX (called_since_last_reset),
                INDEX (status),
                INDEX (gmt_offset_now),
                INDEX (postal_code),
                INDEX (last_local_call_time),
                INDEX (`rank`),
                INDEX (owner)
            ) ENGINE=MyISAM
        ');
    }

    /**
     * Activate the default manual list (998) for TESTCAMP.
     */
    private function seedLeadList(): void
    {
        DB::connection('vicidial')->table('vicidial_lists')
            ->where('list_id', 998)
            ->update([
                'active' => 'Y',
                'list_description' => 'Test leads for TESTCAMP',
                'list_changedate' => now()->toDateTimeString(),
            ]);
    }

    /**
     * Insert test leads into vicidial_list for manual dialing.
     */
    private function seedLeads(): void
    {
        $db = DB::connection('vicidial');

        // Skip if leads already exist for list 998.
        if ($db->table('vicidial_list')->where('list_id', 998)->exists()) {
            return;
        }

        $leads = [
            ['first_name' => 'John',    'last_name' => 'Smith',    'phone_number' => '5551001001', 'state' => 'CA', 'city' => 'Los Angeles'],
            ['first_name' => 'Maria',   'last_name' => 'Garcia',   'phone_number' => '5551001002', 'state' => 'TX', 'city' => 'Houston'],
            ['first_name' => 'James',   'last_name' => 'Johnson',  'phone_number' => '5551001003', 'state' => 'FL', 'city' => 'Miami'],
            ['first_name' => 'Linda',   'last_name' => 'Williams', 'phone_number' => '5551001004', 'state' => 'NY', 'city' => 'New York'],
            ['first_name' => 'Robert',  'last_name' => 'Brown',    'phone_number' => '5551001005', 'state' => 'IL', 'city' => 'Chicago'],
            ['first_name' => 'Patricia', 'last_name' => 'Jones',    'phone_number' => '5551001006', 'state' => 'AZ', 'city' => 'Phoenix'],
            ['first_name' => 'Michael', 'last_name' => 'Davis',    'phone_number' => '5551001007', 'state' => 'PA', 'city' => 'Philadelphia'],
            ['first_name' => 'Barbara', 'last_name' => 'Miller',   'phone_number' => '5551001008', 'state' => 'WA', 'city' => 'Seattle'],
            ['first_name' => 'William', 'last_name' => 'Wilson',   'phone_number' => '5551001009', 'state' => 'CO', 'city' => 'Denver'],
            ['first_name' => 'Elizabeth', 'last_name' => 'Moore',   'phone_number' => '5551001010', 'state' => 'GA', 'city' => 'Atlanta'],
        ];

        $now = now()->toDateTimeString();

        foreach ($leads as $lead) {
            $db->table('vicidial_list')->insert([
                'entry_date' => $now,
                'status' => 'NEW',
                'list_id' => 998,
                'entry_list_id' => 998,
                'phone_code' => '1',
                'phone_number' => $lead['phone_number'],
                'first_name' => $lead['first_name'],
                'last_name' => $lead['last_name'],
                'city' => $lead['city'],
                'state' => $lead['state'],
                'country_code' => 'US',
                'gmt_offset_now' => -5.00,
                'called_since_last_reset' => 'N',
                'called_count' => 0,
                'rank' => 0,
                'owner' => '',
            ]);
        }
    }

    private function updateLocalUserCredentials(): void
    {
        User::where('email', 'test@example.com')->update([
            'vicidial_user' => 'agent1',
            'vicidial_pass' => 'Test123!',
            'vicidial_phone_login' => '1001',
            'vicidial_phone_pass' => '1234',
        ]);
    }
}
