<?php

namespace App\Http\Controllers;

use App\Article;
use DateTime;
use Feed;
use Goutte\Client;
use Sunra\PhpSimple\HtmlDomParser;
use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\Classification\Ensemble\RandomForest;
use Phpml\Classification\KNearestNeighbors;
use Phpml\Dataset\ArrayDataset;
//use NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;

class ArticleController extends Controller
{

    CONST ARTICLES_LIMIT = 2;
    CONST ARTICLES_PAGE = 1;

    public function update() {
        // 200 max
        $sources = [
            [
                'name' => 'clarin',
                'url' => 'https://www.clarin.com/dinreq/sas/paginator/2/NWS/latest/2-result.json?tpl=desgenericolistadoautomaticov3mo_subhome_3col_iscroll.tpl&pages=10&start=8&share_view=0&limit=' . self::ARTICLES_LIMIT. '&sectionId=2&page=' . self::ARTICLES_PAGE,
                'classification' => 'politica'
            ],
            [
                // https://www.clarin.com/dinreq/sas/paginator/701/NWS/latest/701-result.json?includeDescendant=true&tpl=desgenericolistadoautomaticov3mo_subhome_3col_iscroll.tpl&pages=10&start=20&share_view=0&limit=12&sectionId=701&page=1
                'name' => 'clarin',
                'url' => 'https://www.clarin.com/dinreq/sas/paginator/701/NWS/latest/701-result.json?includeDescendant=true&tpl=desgenericolistadoautomaticov3mo_subhome_3col_iscroll.tpl&pages=10&start=20&share_view=0&limit=' . self::ARTICLES_LIMIT. '&sectionId=701&page=' . self::ARTICLES_PAGE,
                'classification' => 'economia'
            ],
            [
                'name' => 'clarin',
                'url' => 'https://www.clarin.com/dinreq/sas/paginator/10471/NWS/latest/6130-result.json?includeDescendant=true&tpl=desgenericolistadoautomaticov3mo_subhome_3col_iscroll.tpl&pages=10&start=8&share_view=0&limit=' . self::ARTICLES_LIMIT. '&sectionId=10471&page=' . self::ARTICLES_PAGE,
                'classification' => 'tecnologia'
            ],
            [
                'name' => 'clarin',
                'url' => 'https://www.clarin.com/dinreq/sas/paginator/3/NWS/latest/3-result.json?moduleTitle=M%C3%81S%20NOTICIAS&includeDescendant=true&tpl=desgenericolistadoautomaticov3mo_subhome_iscroll.tpl&pages=10&start=0&share_view=0&limit=' . self::ARTICLES_LIMIT. '&sectionId=3&page=' . self::ARTICLES_PAGE,
                'classification' => 'deportes'
            ],
        ];

        foreach ($sources as $source) {
            $articlesLink = $this->getArticlesLink($source['url']);

            foreach ($articlesLink as $link) {
                $this->getArticlesContent($link->href, $source);
                sleep(5);
            }

        }

        die;

        foreach ($sources as $source) {

            $rss = Feed::loadRss($source[1]);

            foreach ($rss->item as $item) {
                $article = new Article();
                $article->title = $item->title;
                $article->description = $item->description;
                $article->content = $item->{'content:encoded'};
                $article->author = $item->author;
                $article->url = $item->url;
                $article->source = $source[0];
                $article->date = $item->pubDate;
                $article->classification = $source[2];
                $article->save();
            }

        }

    }

    public function index() {

        $articles = Article::all();
        $samples = $articles->pluck('title')->all();
        $labels = $articles->pluck('classification')->all();

//        // PREDICTION
        $predictionSamples = [
            'Según se dio a conocer ayer en el sitio de "Haven", así se llama la aplicación, esta herramienta de código abierto fue diseñada para activistas de derechos humanos y otras personas que están en riesgo y utiliza los sensores de un teléfono con sistema Android para detectar cambios en una habitación. ',
        ];
//
        $vectorizer = new TokenCountVectorizer(new WhitespaceTokenizer());
//
//        // Build the dictionary.
        $vectorizer->fit($samples);
//
//        // Transform the provided text samples into a vectorized list.
        $vectorizer->transform($samples);
//
//        // Build the dictionary.
        $vectorizer->fit($predictionSamples);
//
//        // Transform the provided text samples into a vectorized list.
        $vectorizer->transform($predictionSamples);

        $classifier = new NaiveBayes();
        $classifier->train($samples, $labels);

        $prediction = $classifier->predict($predictionSamples);
//        dd($samples, $labels, $predictionSamples, $prediction);

        dd($prediction);

    }

    public function getArticlesLink($url) {
        $html = HtmlDomParser::file_get_html($url);
        return $html->find('a');
    }

    /**
     * @param $url
     */
    public function getArticlesContent($url, $source) {
        $client = new Client();

        $crawler = $client->request('GET', 'https://www.clarin.com' . $this->curateHref($url));

        $article = new Article();

        $crawler->filter('#title')->each(function ($node) use(&$article){
            $article->title = $node->text();
        });
        $crawler->filter('.bajada p')->each(function ($node) use(&$article){
            $article->content = $node->text();
        });
        $crawler->filter('.body-nota span p')->each(function ($node) use(&$article){
            $article->content .= ' ' . $node->text();
        });
        $crawler->filter('.author-name .info p')->each(function ($node) use(&$article){
            $article->author = $node->text();
        });
        $crawler->filter('.breadcrumb span')->first(function ($node) use(&$article){
            $timestamp = $node->attr('data-momento-timestamp');
            $article->date = new DateTime("@$timestamp");
        });

        $article->url = 'https://www.clarin.com' . $this->curateHref($url);
        $article->source = $source['name'];
        $article->classification = $source['classification'];
        $article->save();

    }

    public function curateHref($url) {
        return str_replace('"', '', stripslashes($url));
    }
}