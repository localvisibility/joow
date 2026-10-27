<?php

namespace App\Services\Modules;

use App\Models\Site;

/**
 * Génère les mentions légales + politique de confidentialité (RGPD)
 * à partir des informations saisies dans le module "legal".
 */
class LegalGenerator
{
    public static function defaults(): array
    {
        return [
            'enabled'        => false,
            'company_name'   => '', 'company_type' => 'Entreprise individuelle', 'capital' => '',
            'siret'          => '', 'rcs' => '', 'tva' => '', 'director' => '',
            'address'        => '', 'email' => '', 'phone' => '',
            'host_name'      => 'GMD COM (service Joow), RCS Paris 882 334 733, sur serveurs Hostinger International Ltd.', 'host_address' => '70 rue Damrémont, 75018 Paris (serveurs : 61 Lordou Vironos Street, 6023 Larnaca, Chypre, Union européenne)',
            'custom_mentions' => '', 'custom_privacy' => '',
        ];
    }

    public function html(Site $site): string
    {
        $l = array_replace(self::defaults(), $site->module('legal'));
        $b = $site->site_data['business'] ?? [];
        $name = $l['company_name'] ?: ($b['name'] ?? $site->name);
        $addr = $l['address'] ?: ($b['address'] ?? '');
        $mail = $l['email'] ?: ($b['email'] ?? $site->owner_email ?? '');
        $tel  = $l['phone'] ?: ($b['phone'] ?? '');
        $e = fn ($v) => e((string) $v);
        $line = fn ($label, $v) => $v ? "<p><strong>{$label} :</strong> ".$e($v).'</p>' : '';

        return <<<HTML
<h2>Mentions légales</h2>
<h3>Éditeur du site</h3>
{$line('Dénomination', $name)}{$line('Forme juridique', $l['company_type'])}{$line('Capital social', $l['capital'])}
{$line('SIRET', $l['siret'])}{$line('RCS', $l['rcs'])}{$line('N° TVA intracommunautaire', $l['tva'])}
{$line('Directeur de la publication', $l['director'] ?: $name)}
{$line('Adresse', $addr)}{$line('Email', $mail)}{$line('Téléphone', $tel)}
<h3>Hébergement</h3>
{$line('Hébergeur', $l['host_name'])}{$line('Adresse', $l['host_address'])}
<h3>Propriété intellectuelle</h3>
<p>L'ensemble des contenus de ce site (textes, images, logos) est la propriété de {$e($name)} ou de ses partenaires et est protégé par le droit d'auteur. Toute reproduction sans autorisation est interdite.</p>
<h3>Responsabilité</h3>
<p>Les informations publiées sont fournies à titre indicatif. {$e($name)} ne saurait être tenu responsable des erreurs, omissions ou de l'utilisation faite de ces informations.</p>
{$e($l['custom_mentions'])}
<h2>Politique de confidentialité</h2>
<p>Les données personnelles collectées via les formulaires de ce site (nom, téléphone, email, message, date et détails de votre demande) sont destinées exclusivement à {$e($name)}, responsable du traitement, afin de répondre à vos demandes (réservation, devis, rendez-vous, contact) et, le cas échéant, de vous en confirmer la prise en compte par email ou SMS. Elles ne sont ni vendues ni cédées à des tiers.</p>
<p>Base légale : l'exécution de mesures précontractuelles et contractuelles prises à votre demande, ou votre consentement. Durée de conservation : 3 ans à compter du dernier contact, sauf obligation légale plus longue (facturation).</p>
<p>Sous-traitants : ce site est conçu et hébergé par Joow (GMD COM, Paris), qui héberge les données en Union européenne, les met à disposition de {$e($name)} et envoie les confirmations pour son compte, sans les utiliser à d'autres fins. En cas de paiement en ligne ou d'empreinte bancaire, la transaction est traitée par Stripe ; vos données de carte ne transitent jamais par ce site.</p>
<p>Conformément au RGPD et à la loi Informatique et Libertés, vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation et d'opposition. Pour l'exercer : {$e($mail)}. Vous pouvez également saisir la CNIL (www.cnil.fr).</p>
<p>Ce site utilise uniquement des cookies techniques strictement nécessaires à son fonctionnement et une mesure d'audience sans cookie ni identifiant individuel. Aucun traceur publicitaire n'est déposé.</p>
<p>Les avis affichés proviennent de la fiche Google de l'établissement et sont reproduits avec le prénom public de leur auteur ; un auteur peut en demander le retrait à l'adresse ci-dessus.</p>
{$e($l['custom_privacy'])}
HTML;
    }
}
