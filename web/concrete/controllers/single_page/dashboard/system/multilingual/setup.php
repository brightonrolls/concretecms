<?php

namespace Concrete\Controller\SinglePage\Dashboard\System\Multilingual;
use \Concrete\Core\Page\Controller\DashboardPageController;
use Core;
use Concrete\Core\Multilingual\Page\Section\Section;
use Config;
use Localization;
use Loader;
use Page;

defined('C5_EXECUTE') or die("Access Denied.");

class Setup extends DashboardPageController
{

    public $helpers = array('form');
    protected $pagesToCopy = array();

    public function view()
    {
        $ll = Core::make('localization/languages');
        $cl = Core::Make('lists/countries');
        $languages = $ll->getLanguageList();

        $this->set('pages', Section::getList());
        $this->set('languages', $languages);
        $this->set('countries', $cl->getCountries());
        $this->set('ch', Core::make('multilingual/interface/flag'));

        $this->set('defaultLocale', Config::get('concrete.multilingual.default_locale'));
        $this->set('redirectHomeToDefaultLocale', Config::get('concrete.multilingual.redirect_home_to_default_locale'));
        $this->set('useBrowserDetectedLocale', Config::get('concrete.multilingual.use_browser_detected_locale'));
    }

    protected function populateCopyArray($startingPage)
    {
        $db = Loader::db();
        if ($startingPage->isAlias()) {
            $cID = $startingPage->getCollectionPointerOriginalID();
        } else {
            $cID = $startingPage->getCollectionID();
        }

        $q = "select cID from Pages where cParentID = ? order by cDisplayOrder asc";
        $r = $db->query($q, array($cID));
        while ($row = $r->fetchRow()) {
            $c = Page::getByID($row['cID'], 'RECENT');
            if (!$c->getAttribute('multilingual_exclude_from_copy')) {
                $this->pagesToCopy[] = $c;
                $this->populateCopyArray($c);
            }
        }
    }

    public function load_icon()
    {

        $ll = Core::make('localization/languages');
        $ch = Core::make('multilingual/interface/flag');
        $msCountry = $this->post('msCountry');

        if (!$msCountry) {
            return false;
        }

        $flag = $ch->getFlagIcon($msCountry);
        if ($flag) {
            $html = $flag;
        } else {
            $html = "<div><strong>" . t('None') . "</strong></div>";
        }

        print $html;
        exit;
    }

    public function multilingual_content_enabled()
    {
        $this->set('message', t('Multilingual content enabled'));
        $this->view();
    }

    public function multilingual_content_updated()
    {
        $this->set('message', t('Multilingual content updated'));
        $this->view();
    }

    public function tree_copied()
    {
        $this->set('message', t('Multilingual tree copied.'));
        $this->view();
    }

    public function locale_section_removed()
    {
        $this->set('message', t('Section removed.'));
        $this->view();
    }

    public function default_locale_updated()
    {
        $this->set('message', t('Default Section settings updated.'));
        $this->view();
    }

    public function set_default()
    {
        if (Loader::helper('validation/token')->validate('set_default')) {
            $lc = Section::getByLocale($this->post('defaultLocale'));
            if (is_object($lc)) {
                Config::save('concrete.multilingual.default_locale', $this->post('defaultLocale'));
                Config::save('concrete.multilingual.redirect_home_to_default_locale', $this->post('redirectHomeToDefaultLocale'));
                Config::save('concrete.multilingual.use_browser_detected_locale', $this->post('useBrowserDetectedLocale'));
                $this->redirect('/dashboard/system/multilingual/setup', 'default_locale_updated');
            } else {
                $this->error->add(t('Invalid Section'));
            }
        } else {
            $this->error->add(Loader::helper('validation/token')->getErrorMessage());
        }
        $this->view();
    }

    public function remove_locale_section($sectionID = false, $token = false)
    {
        if (Loader::helper('validation/token')->validate('', $token)) {
            $lc = Section::getByID($sectionID);
            if (is_object($lc)) {

                $lc->unassign();
                $this->redirect('/dashboard/system/multilingual/setup', 'locale_section_removed');

            } else {
                $this->error->add(t('Invalid section'));
            }
        } else {
            $this->error->add(Loader::helper('validation/token')->getErrorMessage());
        }
        $this->view();
    }

    public function add_content_section()
    {
        if (Loader::helper('validation/token')->validate('add_content_section')) {
            if ((!Loader::helper('validation/numbers')->integer($this->post('pageID'))) || $this->post('pageID') < 1) {
                $this->error->add(t('You must specify a page for this multilingual content section.'));
            } else {
                $pc = Page::getByID($this->post('pageID'));
            }

            if (!$this->error->has()) {
                $lc = Section::getByID($this->post('pageID'));
                if (is_object($lc)) {
                    $this->error->add(t('A multilingual section page at this location already exists.'));
                }
            }

            if (!$this->error->has()) {
                if ($this->post('msLanguage')) {
                    $combination = $this->post('msLanguage') . '_' . $this->post('msCountry');
                    $locale = Section::getByLocale($combination);
                    if (is_object($locale)) {
                        $this->error->add(t('This language/region combination already exists.'));
                    }
                }
            }

            if (!$this->error->has()) {
                Section::assign($pc, $this->post('msLanguage'), $this->post('msCountry'));
                $this->redirect('/dashboard/system/multilingual/setup', 'multilingual_content_updated');
            }
        } else {
            $this->error->add(Loader::helper('validation/token')->getErrorMessage());
        }
        $this->view();
    }

}
