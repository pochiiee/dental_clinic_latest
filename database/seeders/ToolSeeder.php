<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run(): void
    {
        $tools = [
            'Acrylic resin',
            'Archwire',
            'Articulating Paper',
            'Artificial teeth set',
            'Bands',
            'Bleaching agent',
            'Bracket holder',
            'Bracket holding tools',
            'Burnisher',
            'Ceramic brackets',
            'Cephalometric unit',
            'Cephalostat',
            'Cheek Retractor',
            'Cold-light or LED whitening lamp',
            'Composite filling material',
            'Cord Packer',
            'Cotton pliers',
            'Cotton rolls',
            'Curettes',
            'Curing light',
            'Dental Elevators (Straight, Periosteal)',
            'Dental Explorer (probe)',
            'Dental stone',
            'Dental Tray',
            'Diagnostic Aids',
            'Distal End Cutter',
            'Endodontic files',
            'Excavator',
            'Extraction Forceps',
            'Film holder',
            'Fine burs',
            'Finishing Burs',
            'Finishing discs',
            'Gauze',
            'Gingival Retraction Cord',
            'Gloves',
            'Gutta-percha',
            'Handpiece',
            'Head support',
            'Impression Trays',
            'Intraoral X-ray unit',
            'Irrigating solution',
            'Lead apron',
            'Light cure machine',
            'Ligature cutter',
            'Local Anesthesia Syringe',
            'Mask',
            'Mixing bowl',
            'Mouth mirror',
            'Mouth retractor',
            'Orthodontic Adhesive',
            'Orthodontic Brackets',
            'Orthodontic pliers',
            'Panoramic X-ray unit',
            'Paper points',
            'Paste',
            'Periodontal probe',
            'Periosteal elevator',
            'Plugger',
            'Polishing lathe',
            'Polishing Tools',
            'Polishers',
            'Protective gear',
            'Retraction cord',
            'Rubber dam',
            'Rubber prophy cups',
            'Scaler',
            'Self-Ligating Brackets',
            'Sensor',
            'Shade guide',
            'Spatula',
            'Spreader',
            'Straight elevator',
            'Suction',
            'Suction/ejector',
            'Suture materials',
            'Syringe (with anesthesia)',
            'TMJ X-ray unit',
            'Trimmer',
            'Tweezers/pliers',
            'Ultrasonic scaler',
            'Veneer Placement & Bonding Kits',
            'Wax rim',
            'Whitening gel',
        ];

        foreach ($tools as $tool) {
            DB::table('tools')->insert([
                'tool_name' => $tool,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
