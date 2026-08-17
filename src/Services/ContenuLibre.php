<?php

/*
 * Addon Supervio pour ClientXCMS.
 *
 * Nettoyage du CSS et du pied de page personnalisés.
 *
 * Ces deux réglages sont saisis par un administrateur, donc par quelqu'un qui a
 * déjà les pleins pouvoirs sur son site : il ne s'agit pas de s'en protéger.
 * Le risque réel est ailleurs — un contenu mal formé ferme prématurément la
 * balise <style> ou le contexte HTML, et la page publique devient un vecteur
 * pour n'importe quel visiteur, y compris via un compte staff compromis ou une
 * valeur écrite directement en base.
 *
 * On neutralise donc les sorties de contexte, sans chercher à brider la
 * créativité de l'administrateur sur le reste.
 */

namespace App\Addons\Supervio\Services;

class ContenuLibre
{
    /** Balises tolérées dans un pied de page personnalisé. */
    private const BALISES_PIED = '<a><strong><b><em><i><br><span><small>';

    /**
     * CSS injectable dans une balise <style>.
     *
     * Trois neutralisations :
     *  - « </style » referme la balise et laisse écrire du HTML libre derrière ;
     *  - « <script » n'a rien à faire dans une feuille de style ;
     *  - « expression() » et « javascript: » sont des exécutions de script
     *    héritées d'anciens navigateurs, sans usage légitime ici.
     */
    public static function css(string $brut): string
    {
        /* On consomme la balise entière, chevrons compris. Ne retirer que
           « </style » laisserait « > » et « </script> » traîner dans la feuille :
           inerte, puisque seul « </style » referme l'élément, mais illisible et
           inquiétant pour qui relit le réglage.

           Le « > » seul n'est pas filtrable : c'est le combinateur enfant du
           CSS, « .parent > .enfant ». */
        $css = preg_replace('#</?\s*(style|script)[^>]*>?#i', '', $brut) ?? '';
        $css = preg_replace('#expression\s*\([^)]*\)?#i', '', $css) ?? '';
        $css = preg_replace('#javascript\s*:#i', '', $css) ?? '';

        return trim($css);
    }

    /**
     * Pied de page personnalisé.
     *
     * Une liste blanche de balises plutôt qu'un filtrage de ce qui est interdit :
     * on sait ce qu'on autorise, on ne court pas après ce qu'on aurait oublié.
     * Les attributs d'événement (onclick et consorts) et les URL « javascript: »
     * sont retirés, un lien restant un lien.
     */
    public static function pied(string $brut): string
    {
        $html = strip_tags($brut, self::BALISES_PIED);
        $html = preg_replace('#\son\w+\s*=\s*(["\']).*?\1#is', '', $html) ?? '';
        $html = preg_replace('#\son\w+\s*=\s*[^\s>]+#i', '', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $html) ?? '';

        return trim($html);
    }
}
