<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceToolSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        /**
         * Each service_id corresponds to your 18 dental procedures.
         * Adjust the IDs below if they differ in your database.
         */
        $data = [

            // ========================================
            // 1. Mouth Examination
            // ========================================
            1 => [
                'Mouth mirror',
                'Dental Explorer (probe)',
                'Cotton pliers',
                'Periodontal probe',
                'Dental Tray',
                'Gloves',
                'Mask',
            ],

            // ========================================
            // 2. Oral Prophylaxis (Cleaning)
            // ========================================
            2 => [
                'Scaler',
                'Curettes',
                'Ultrasonic scaler',
                'Rubber prophy cups',
                'Paste',
                'Suction/ejector',
            ],

            // ========================================
            // 3. Digital Panoramic X-ray
            // ========================================
            3 => [
                'Panoramic X-ray unit',
                'Sensor',
                'Lead apron',
            ],

            // ========================================
            // 4. Digital Cephalometric X-ray
            // ========================================
            4 => [
                'Cephalometric unit',
                'Cephalostat',
                'Sensor',
            ],

            // ========================================
            // 5. TMJ X-ray
            // ========================================
            5 => [
                'TMJ X-ray unit',
                'Head support',
                'Lead apron',
            ],

            // ========================================
            // 6. Periapical X-ray
            // ========================================
            6 => [
                'Intraoral X-ray unit',
                'Film holder',
                'Sensor',
            ],

            // ========================================
            // 7. Tooth Restoration (Filling)
            // ========================================
            7 => [
                'Mouth mirror',
                'Dental Explorer (probe)',
                'Excavator',
                'Handpiece',
                'Plugger',
                'Burnisher',
                'Light cure machine',
                'Composite filling material',
            ],

            // ========================================
            // 8. Teeth Whitening
            // ========================================
            8 => [
                'Cold-light or LED whitening lamp',
                'Whitening gel',
                'Bleaching agent',
                'Mouth retractor',
                'Protective gear',
                'Shade guide',
                'Cotton rolls',
                'Gauze',
                'Suction',
            ],

            // ========================================
            // 9. Tooth Extraction
            // ========================================
            9 => [
                'Mouth mirror',
                'Dental Explorer (probe)',
                'Cotton pliers',
                'Syringe (with anesthesia)',
                'Periosteal elevator',
                'Straight elevator',
                'Extraction Forceps',
                'Gauze',
                'Suction',
            ],

            // ========================================
            // 10. Root Canal Treatment
            // ========================================
            10 => [
                'Handpiece',
                'Endodontic files',
                'Syringe (with anesthesia)',
                'Irrigating solution',
                'Paper points',
                'Gutta-percha',
                'Plugger',
                'Spreader',
                'Composite filling material',
            ],

            // ========================================
            // 11. Wisdom Tooth Removal
            // ========================================
            11 => [
                'Local Anesthesia Syringe',
                'Dental Elevators (Straight, Periosteal)',
                'Extraction Forceps',
                'Gauze',
                'Suction',
                'Suture materials',
            ],

            // ========================================
            // 12. Crowns and Bridges
            // ========================================
            12 => [
                'Mouth mirror',
                'Dental Explorer (probe)',
                'Tweezers/pliers',
                'Gingival Retraction Cord',
                'Cord Packer',
                'Impression Trays',
                'Articulating Paper',
                'Finishing Burs',
                'Polishing Tools',
            ],

            // ========================================
            // 13. Partial Dentures
            // ========================================
            13 => [
                'Cotton pliers',
                'Impression Trays',
                'Mixing bowl',
                'Spatula',
                'Finishing Burs',
                'Polishing Tools',
            ],

            // ========================================
            // 14. Complete Dentures
            // ========================================
            14 => [
                'Impression Trays',
                'Mixing bowl',
                'Spatula',
                'Dental stone',
                'Wax rim',
                'Artificial teeth set',
                'Acrylic resin',
                'Handpiece',
                'Trimmer',
                'Polishing lathe',
            ],

            // ========================================
            // 15. Dental Veneers
            // ========================================
            15 => [
                'Shade guide',
                'Diagnostic Aids',
                'Retraction cord',
                'Rubber dam',
                'Fine burs',
                'Veneer Placement & Bonding Kits',
                'Finishing discs',
                'Polishers',
                'Articulating Paper',
            ],

            // ========================================
            // 16. Metal Braces
            // ========================================
            16 => [
                'Mouth mirror',
                'Dental Explorer (probe)',
                'Bracket holder',
                'Scaler',
                'Orthodontic Brackets',
                'Bands',
                'Archwire',
                'Ligature cutter',
                'Orthodontic pliers',
                'Curing light',
            ],

            // ========================================
            // 17. Ceramic Braces
            // ========================================
            17 => [
                'Orthodontic pliers',
                'Ligature cutter',
                'Bracket holding tools',
                'Ceramic brackets',
                'Archwire',
            ],

            // ========================================
            // 18. SWLF Braces
            // ========================================
            18 => [
                'Cheek Retractor',
                'Cotton pliers',
                'Bracket holder',
                'Orthodontic Adhesive',
                'Light cure machine',
                'Self-Ligating Brackets',
                'Archwire',
                'Scaler',
                'Distal End Cutter',
            ],
        ];

        // ========================================
        // Insert all relationships
        // ========================================
        foreach ($data as $serviceId => $tools) {
            foreach ($tools as $toolName) {
                $tool = DB::table('tools')->where('tool_name', 'LIKE', "%{$toolName}%")->first();

                if ($tool) {
                    DB::table('service_tools')->insert([
                        'service_id' => $serviceId,
                        'tool_id' => $tool->tool_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    echo "⚠️ Tool not found: {$toolName}\n";
                }
            }
        }
    }
}
