<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Déduit le secteur d'un établissement depuis son nom + ses types Google.
 * Repris de la logique des templates Local Visibility.
 */
class SectorDetector
{
    /** secteur => mots-clés (nom / types) */
    private const MAP = [
        'restaurant'  => ['restaurant', 'food', 'bar', 'cafe', 'boulanger', 'patisser', 'traiteur', 'pizz', 'brasserie'],
        'hebergement' => ['hotel', 'lodging', 'gite', 'camping', 'chambre', 'maison_hote', 'bed_and_breakfast'],
        'renovation'  => ['isolation', 'chaudiere', 'pompe', 'vmc', 'couvreur', 'macon', 'carreleur', 'peintre', 'menuisier', 'fenetre', 'cuisiniste', 'climatisation', 'chauffage', 'panneaux', 'solaire', 'renovation', 'energ'],
        'artisan'     => ['plombier', 'plumber', 'electricien', 'electrician', 'serrurier', 'locksmith', 'chauffagiste', 'vitrier', 'multiservice', 'bricolage'],
        'automobile'  => ['garage', 'car_repair', 'carrosserie', 'pneu', 'moto', 'car_dealer'],
        'sante'       => ['medecin', 'doctor', 'dentist', 'dentiste', 'kine', 'osteo', 'podolog', 'psycho', 'infirmier', 'opticien', 'pharmac', 'veterinaire', 'physiotherapist', 'health'],
        'bienetre'    => ['sophrolog', 'hypno', 'naturopath', 'yoga', 'masseur', 'spa', 'reflexolog', 'chiropract', 'coach'],
        'beaute'      => ['coiffeur', 'hair_care', 'barbier', 'esthet', 'beauty_salon', 'onglerie', 'tatoueur', 'nail'],
        'avocat'      => ['avocat', 'lawyer', 'notaire', 'notary', 'juridique', 'huissier'],
        'immobilier'  => ['immobili', 'real_estate', 'agence', 'syndic'],
        'assurance'   => ['assur', 'insurance', 'mutuelle', 'courtier'],
        'comptable'   => ['comptab', 'accounting', 'expert-compt', 'fiduciaire'],
        'architecte'  => ['architect', 'design', 'decor', 'amenagement'],
        'conseil'     => ['conseil', 'consulting', 'consultant', 'coaching', 'formation', 'marketing'],
        'commerce'    => ['fleuriste', 'florist', 'caviste', 'bijouterie', 'jewelry', 'librairie', 'book_store', 'store'],
        'service'     => ['photographe', 'demenagement', 'pressing', 'jardin', 'paysagiste', 'nettoyage', 'cleaning'],
    ];

    public function detect(string $name, array $types = []): string
    {
        $hay = Str::of($name.' '.implode(' ', $types))->lower()->ascii()->toString();

        $best = 'service';
        $bestScore = 0;
        foreach (self::MAP as $sector => $words) {
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($hay, $w)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $sector;
            }
        }

        return $bestScore > 0 ? $best : 'service';
    }
}
