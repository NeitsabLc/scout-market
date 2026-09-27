<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GrilleMenu;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FichesRecettesPdf
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly AffichageQuantite $affichageQuantite,
        private readonly DescriptionRecetteSanitizer $descriptionSanitizer,
    ) {
    }

    /** @param list<Menu> $menus */
    public function generer(GrilleMenu $grille, array $menus): string
    {
        $menus = array_values(array_filter(
            $menus,
            static fn (Menu $menu): bool => null !== $menu->getDateMenu()
                && null !== $menu->getTypeRepas()
                && !$menu->getDenrees()->isEmpty(),
        ));

        $options = new Options();
        $repertoireTemporaire = sys_get_temp_dir();
        $options->setTempDir($repertoireTemporaire);
        $options->setFontDir($repertoireTemporaire);
        $options->setFontCache($repertoireTemporaire);
        $options->setChroot($this->projectDir);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Sarabun');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($this->html($grille, $menus), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param list<Menu> $menus */
    private function html(GrilleMenu $grille, array $menus): string
    {
        $nombrePages = count($menus) + 1;
        $pages = [$this->pageGrille($grille, $menus, $nombrePages)];
        foreach ($menus as $index => $menu) {
            $pages[] = $this->pageRepas($menu, $index + 2, $nombrePages);
        }

        return sprintf(
            '<!doctype html><html lang="fr"><head><meta charset="UTF-8"><style>%s</style></head><body>%s</body></html>',
            $this->css(),
            implode('', $pages),
        );
    }

    /** @param list<Menu> $menus */
    private function pageGrille(GrilleMenu $grille, array $menus, int $nombrePages): string
    {
        $jours = iterator_to_array(new \DatePeriod(
            $grille->getDateDebut(),
            new \DateInterval('P1D'),
            $grille->getDateFin()->modify('+1 day'),
        ));
        $repas = [];
        $menusParCase = [];
        foreach ($menus as $menu) {
            $typeRepas = $menu->getTypeRepas();
            $date = $menu->getDateMenu();
            if (null === $typeRepas || null === $date) {
                continue;
            }
            $repas[(string) $typeRepas->getId()] = $typeRepas;
            $menusParCase[$date->format('Y-m-d')][$typeRepas->getCode()] = $menu;
        }
        uasort($repas, static fn ($a, $b): int => $a->getOrdre() <=> $b->getOrdre());

        $entetes = '<th class="corner">Repas</th>';
        foreach ($jours as $jour) {
            $entetes .= sprintf(
                '<th><strong>%s</strong><span>%s</span></th>',
                $this->e($this->jour($jour)),
                $jour->format('d/m'),
            );
        }

        $lignes = '';
        foreach ($repas as $typeRepas) {
            $code = $typeRepas->getCode();
            $cellules = sprintf('<th class="meal-label">%s</th>', $this->e($typeRepas->getLibelle()));
            foreach ($jours as $jour) {
                $menu = $menusParCase[$jour->format('Y-m-d')][$code] ?? null;
                if (!$menu instanceof Menu) {
                    $cellules .= '<td class="empty">-</td>';
                    continue;
                }
                $elements = array_map(
                    fn (string $element): string => '<li>'.$this->e($element).'</li>',
                    $this->elementsMenu($menu),
                );
                $cellules .= sprintf(
                    '<td class="menu-cell">%s<ul>%s</ul></td>',
                    $this->pictoViande($menu),
                    implode('', $elements),
                );
            }
            $lignes .= '<tr>'.$cellules.'</tr>';
        }
        if ('' === $lignes) {
            $lignes = sprintf('<tr><td class="no-menu" colspan="%d">Aucun repas renseigné pour cette grille.</td></tr>', count($jours) + 1);
        }

        return sprintf(
            '<section class="page overview"><header class="document-header"><div><span class="eyebrow">Scout Market</span><h1>%s</h1><p>Du %s au %s</p></div>%s</header><div class="accent-line"></div><table class="menu-grid days-%d"><thead><tr>%s</tr></thead><tbody>%s</tbody></table>%s</section>',
            $this->e($grille->getLabel()),
            $grille->getDateDebut()->format('d/m/Y'),
            $grille->getDateFin()->format('d/m/Y'),
            $this->logo(),
            count($jours),
            $entetes,
            $lignes,
            $this->footer(1, $nombrePages),
        );
    }

    private function pageRepas(Menu $menu, int $numeroPage, int $nombrePages): string
    {
        $date = $menu->getDateMenu();
        $typeRepas = $menu->getTypeRepas();
        if (null === $date || null === $typeRepas) {
            return '';
        }

        $elements = '';
        foreach ($this->groupesRecette($menu) as $groupe) {
            $lignes = '';
            foreach ($groupe['lignes'] as $ligne) {
                $quantites = '';
                foreach ($ligne['quantites'] as $quantite) {
                    $quantites .= sprintf(
                        '<span><b>%s</b> %s</span>',
                        $this->e($quantite['public']),
                        $this->e($quantite['valeur']),
                    );
                }
                $lignes .= sprintf(
                    '<tr><th>%s</th><td><div class="quantities">%s</div></td></tr>',
                    $this->e($ligne['nom']),
                    $quantites,
                );
            }
            $description = null === $groupe['description']
                ? ''
                : '<div class="preparation"><h3>Préparation</h3>'.$groupe['description'].'</div>';
            $categorie = '' === $groupe['categorie']
                ? ''
                : '<div class="category-slot"><span class="category">'.$this->e($this->categorie($groupe['categorie'])).'</span></div>';
            $elements .= sprintf(
                '<article class="recipe-card"><header class="recipe-heading">%s<div class="recipe-title"><h2>%s</h2></div></header><table class="ingredients"><tbody>%s</tbody></table>%s</article>',
                $categorie,
                $this->e($groupe['nom']),
                $lignes,
                $description,
            );
        }

        return sprintf(
            '<section class="page meal-page"><header class="meal-header"><div><span class="eyebrow">%s %s</span><h1>%s</h1>%s</div>%s</header><main class="recipes">%s</main>%s</section>',
            $this->e($this->jour($date)),
            $date->format('d/m/Y'),
            $this->e($typeRepas->getLibelle()),
            null === $menu->getNom() || '' === trim($menu->getNom()) ? '' : '<p>'.$this->e($menu->getNom()).'</p>',
            $this->logo(),
            $elements,
            $this->footer($numeroPage, $nombrePages),
        );
    }

    /** @return list<string> */
    private function elementsMenu(Menu $menu): array
    {
        $elements = [];
        $recettesVues = [];
        foreach ($menu->getDenrees() as $ligne) {
            $recette = $ligne->getRecette();
            if (null === $recette) {
                $elements[] = $ligne->getDenree()->getNom();
                continue;
            }
            $instance = (string) ($ligne->getRecetteInstanceId() ?? $recette->getId());
            if (!isset($recettesVues[$instance])) {
                $recettesVues[$instance] = true;
                $elements[] = $recette->getNom();
            }
        }

        return $elements;
    }

    /**
     * @return list<array{
     *     nom: string,
     *     categorie: string,
     *     description: ?string,
     *     lignes: list<array{nom: string, quantites: list<array{public: string, valeur: string}>}>
     * }>
     */
    private function groupesRecette(Menu $menu): array
    {
        $groupes = [];
        $indexParCle = [];
        foreach ($menu->getDenrees() as $ligne) {
            $recette = $ligne->getRecette();
            $cle = null === $recette
                ? 'denree:'.(string) $ligne->getId()
                : 'recette:'.(string) ($ligne->getRecetteInstanceId() ?? $recette->getId());
            if (!isset($indexParCle[$cle])) {
                $indexParCle[$cle] = count($groupes);
                $groupes[] = [
                    'nom' => $recette?->getNom() ?? $ligne->getDenree()->getNom(),
                    'categorie' => $ligne->getCategorie() ?? '',
                    'description' => null === $recette ? null : $this->descriptionSanitizer->nettoyer($recette->getDescription() ?? ''),
                    'lignes' => [],
                ];
            }
            $groupes[$indexParCle[$cle]]['lignes'][] = $this->ligne($ligne);
        }

        return $groupes;
    }

    /** @return array{nom: string, quantites: list<array{public: string, valeur: string}>} */
    private function ligne(MenuDenree $ligne): array
    {
        $quantites = [];
        /** @var MenuDenreeQuantite $quantite */
        foreach ($ligne->getQuantites() as $quantite) {
            $public = $quantite->getPublicCible();
            $quantites[] = [
                'ordre' => $public->getOrdre(),
                'public' => $public->getLibelle(),
                'valeur' => $this->affichageQuantite->parPersonne($quantite->getQuantiteIndividuelle()).' '.$ligne->getConditionnement()->getSymbole().'/pers.',
            ];
        }
        usort($quantites, static fn (array $a, array $b): int => $a['ordre'] <=> $b['ordre']);

        return [
            'nom' => $ligne->getDenree()->getNom().(null === $ligne->getRegime() ? '' : ' - '.$ligne->getRegime()->libelle()),
            'quantites' => array_map(static fn (array $quantite): array => [
                'public' => $quantite['public'],
                'valeur' => $quantite['valeur'],
            ], $quantites),
        ];
    }

    private function footer(int $page, int $nombrePages): string
    {
        return sprintf(
            '<footer><span>Scout Market - Centre d’activités de Jambville</span><span>Page %d / %d</span></footer>',
            $page,
            $nombrePages,
        );
    }

    private function logo(): string
    {
        $chemin = $this->projectDir.'/assets/images/logo-sgdf-horizontal.png';

        return !is_file($chemin)
            ? '<strong class="brand">SCOUTS ET GUIDES DE FRANCE</strong>'
            : '<img class="logo" src="file://'.str_replace(' ', '%20', $chemin).'" alt="Scouts et Guides de France">';
    }

    private function pictoViande(Menu $menu): string
    {
        $noms = [];
        foreach ($menu->getDenrees() as $ligne) {
            $noms[] = $ligne->getDenree()->getNom();
            if (null !== $ligne->getRecette()) {
                $noms[] = $ligne->getRecette()->getNom();
            }
        }
        $texte = implode(' ', $noms);
        if (1 !== preg_match('/\b(?:agneau|bacon|b[œo]uf|canard|carne|charcuterie|chorizo|dinde|jambon|lardons?|lapin|merguez|mortadelle|mouton|pâté|poulet|porc|rillettes|salami|saucisses?|steak|veau|viande)\b/ui', $texte)) {
            return '';
        }

        $chemin = $this->projectDir.'/assets/images/repas-viande.svg';

        return is_file($chemin)
            ? '<img class="meal-picto" src="file://'.str_replace(' ', '%20', $chemin).'" alt="">'
            : '';
    }

    private function jour(\DateTimeImmutable $date): string
    {
        return ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int) $date->format('w')];
    }

    private function categorie(string $categorie): string
    {
        return match ($categorie) {
            'PETIT_DEJEUNER' => 'Petit-déjeuner',
            'ENTREE' => 'Entrée',
            'PLAT' => 'Plat',
            'FROMAGE' => 'Fromage',
            'DESSERT' => 'Dessert',
            'GOUTER' => 'Goûter',
            default => ucfirst(mb_strtolower($categorie)),
        };
    }

    private function css(): string
    {
        $font = static fn (string $chemin): string => 'file://'.str_replace(' ', '%20', $chemin);

        return sprintf(<<<'CSS'
@font-face { font-family:'Caveat Brush'; src:url('%s') format('truetype'); font-weight:400; }
@font-face { font-family:'Sarabun'; src:url('%s') format('truetype'); font-weight:400; }
@font-face { font-family:'Sarabun'; src:url('%s') format('truetype'); font-weight:700; }
@page { size:A4 portrait; margin:11mm; }
* { box-sizing:border-box; }
html, body { margin:0; color:#003a5d; font-family:'Sarabun', sans-serif; font-size:10px; }
.page { position:relative; min-height:274mm; padding:8mm 7mm 12mm; border:1px dashed #6cbda4; border-radius:10px; page-break-after:always; }
.page:last-child { page-break-after:auto; }
.document-header, .meal-header { display:table; width:100%%; }
.document-header>div, .meal-header>div { display:table-cell; vertical-align:middle; }
.document-header>div:first-child, .meal-header>div:first-child { width:72%%; }
.logo { display:block; width:126px; margin-left:auto; }
.brand { display:block; text-align:right; font-size:11px; }
.eyebrow { color:#0089b7; font-size:9px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; }
h1, h2, h3, p { margin-top:0; }
h1 { margin:2px 0 2px; color:#003a5d; font-family:'Caveat Brush', cursive; font-size:30px; font-weight:400; line-height:1.05; }
.document-header p, .meal-header p { margin:3px 0 0; color:#506b7a; font-size:10px; }
.accent-line { height:5px; margin:7mm 0 6mm; border-radius:3px; background:linear-gradient(to right,#88bd24 0,#88bd24 25%%,#009cd3 25%%,#009cd3 50%%,#f2b233 50%%,#f2b233 75%%,#e9526e 75%%,#e9526e 100%%); }
.menu-grid { width:100%%; border-collapse:separate; border-spacing:2px; table-layout:fixed; font-size:7.2px; }
.menu-grid th, .menu-grid td { padding:5px 3px; border:1px solid #cad7dd; border-radius:4px; vertical-align:top; }
.menu-grid thead th { height:35px; color:#003a5d; background:#e8f2ed; text-align:center; }
.menu-grid thead strong, .menu-grid thead span { display:block; }
.menu-grid thead strong { font-family:'Caveat Brush', cursive; font-size:12px; font-weight:400; text-transform:capitalize; }
.menu-grid .corner { width:58px; background:#003a5d; color:#fff; font-family:'Sarabun', sans-serif; font-size:8px; text-transform:uppercase; }
.menu-grid .meal-label { width:58px; background:#f4f7f8; font-size:8px; vertical-align:middle; }
.menu-grid td { height:72px; color:#153f57; background:#fff; }
.menu-grid .menu-cell { position:relative; padding-top:7px; padding-right:18px; }
.meal-picto { position:absolute; top:4px; right:4px; width:12px; height:12px; }
.menu-grid td.empty { color:#a7b5bc; background:#f5f7f8; text-align:center; vertical-align:middle; }
.menu-grid ul { margin:0; padding:0; list-style:none; }
.menu-grid li { margin-bottom:3px; line-height:1.25; }
.menu-grid li:last-child { margin-bottom:0; }
.menu-grid.days-8, .menu-grid.days-9, .menu-grid.days-10 { font-size:6.4px; }
.no-menu { height:120px!important; text-align:center; vertical-align:middle!important; }
.meal-header h1 { font-size:34px; }
.recipes { width:100%%; margin-top:7mm; }
.recipe-card { margin:0 0 5mm; padding:4mm; border:1px solid #cfdae0; border-left:5px solid #003a5d; border-radius:6px; page-break-inside:avoid; }
.recipe-heading { display:table; width:100%%; margin-bottom:3mm; }
.category-slot, .recipe-title { display:table-cell; vertical-align:middle; }
.category-slot { width:1%%; padding-right:8px; white-space:nowrap; }
.recipe-title h2 { margin:0; color:#003a5d; font-family:'Caveat Brush', cursive; font-size:19px; font-weight:400; line-height:1.1; }
.category { display:inline-block; padding:2px 7px; border-radius:9px; color:#003a5d; background:#e8f2ed; font-size:7px; font-weight:700; letter-spacing:.7px; text-transform:uppercase; }
.ingredients { width:100%%; border-collapse:collapse; table-layout:fixed; }
.ingredients th, .ingredients td { padding:5px 6px; border-top:1px solid #e2e9ec; text-align:left; vertical-align:top; }
.ingredients th { width:31%%; color:#003a5d; font-size:9px; }
.quantities span { display:inline-block; margin:0 5px 3px 0; padding:3px 5px; border-radius:10px; color:#274f64; background:#f0f5f7; font-size:7.5px; white-space:nowrap; }
.quantities b { color:#00729b; }
.preparation { margin-top:3mm; padding:3mm 4mm; border-radius:5px; color:#244c61; background:#f7faf8; line-height:1.4; }
.preparation h3 { margin:0 0 2mm; color:#003a5d; font-family:'Caveat Brush', cursive; font-size:15px; font-weight:400; }
.preparation p { margin:0 0 4px; }
.preparation ul, .preparation ol { margin:3px 0 0; padding-left:17px; }
.preparation li { margin-bottom:2px; }
footer { position:absolute; right:7mm; bottom:4mm; left:7mm; display:table; padding-top:3px; border-top:1px solid #dbe5e8; color:#6c818c; font-size:7px; }
footer span { display:table-cell; }
footer span:last-child { text-align:right; }
CSS,
            $font($this->projectDir.'/assets/fonts/caveat-brush/CaveatBrush-Regular.ttf'),
            $font($this->projectDir.'/assets/fonts/sarabun/Sarabun-Regular.ttf'),
            $font($this->projectDir.'/assets/fonts/sarabun/Sarabun-Bold.ttf'),
        );
    }

    private function e(string $valeur): string
    {
        return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
