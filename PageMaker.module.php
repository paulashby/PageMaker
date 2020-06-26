<?php namespace ProcessWire;
class PageMaker extends WireData implements Module {
  public static function getModuleInfo() {
    return [
      'title' => 'Page Maker',
      'summary' => 'Provides the ability to create and manage a set of pages',
      'author' => 'Paul Ashby, primitive.co',
      'version' => 1
    ];
  }

  public function init() {
     $this->addHookAfter("Modules::uninstall", $this, "customUninstall");
  }
/**
 * Check there are no naming collisions before completing installation
 *
 * @param array $setup Array of elements to check ['fields' Array of strings , 'templates' Array of strings]
 * @return array of errors or boolean true
 */
  protected function preflightInstall($setup) {

    $errors = array(
      'fields' => array(),
      'templates' => array()
    );

    // Check if fields exist
    foreach($setup['fields'] as $f => $spec) {
      $curr_f = wire('fields')->get($f);
      
      if($curr_f !== null) {
        $errors['fields'][] = $f;
      }
    }

    foreach ($setup['templates'] as $t => $spec) {
      $curr_t = $this->templates->get($t);

      if( $curr_t !== null) {
        $errors['templates'][] = $t;
      }
    }
    if(count($errors['fields']) || count($errors['templates'])) {
      // return $errors;
      $mssgs = array();
      foreach ($errors as $elmts => $elnames) {
        if(count($elnames)){
          $mssgs[] = "The following $elmts could not be created as they already exist: " .implode(", ", $elnames);
        }
      }
      return $mssgs;
    }
    return true;
  }
/**
 * Create set of pages
 *
 * @param Array $data Array of elements to create [
 *        'fields' [field_name => ['fieldtype', string 'label']] , 
 *        'templates' [template_name =>['t_children'=>[Strings], 't_parents'=>[Strings], 't_fields'=>[Strings]]]
 *        'pages' [page_name => ['template' => String, 'parent' => String (path of page), 'title' => String]]
 * @param Array $names field_name_from_data_param => name_string ($data being first parameter)
 * @return array of errors or boolean true
 */
  public function makePages($setup) {

    $installable = $this->preflightInstall(array('fields' => $setup['fields'], 'templates' => $setup['templates']));

    if($installable === true){

      foreach ($setup['fields'] as $key => $spec) {
        $spec['name'] = $key;
        $this->makeField($spec);
      }
      foreach ($setup['templates'] as $key => $spec) { 
        $spec['name'] = $key;
        $this->makeTemplate($spec);
      }
      foreach ($setup['templates'] as $key => $spec) {
        $spec['name'] = $key;
        $this->setTemplateFamily($spec);
      }
      foreach ($setup['pages'] as $key => $spec) {
        $spec['name'] = $key;
        $this->makePage($spec);
      }

      //TODO: This overwrites the record of any previously-created pages - better to add to existing? (And throw an exception if given existing page name.)

      // Store $setup
      $data = $this->modules->getConfig($this->className);
      $data['setup'] = $setup;
      $this->modules->saveConfig($this->className, $data);

      return true;

    } else {
      // preflightInstall will return array of errors: ('fields'=>[], 'templates')
      return $installable;
    }
  }
  /**
 * Make a field
 *
 * @param string $key Name of field
 * @param array $spec [string 'fieldtype', string 'label']
 * @return object The new field
 */
  protected function makeField($spec) {
    
    $f = new Field();
    $f->type = $spec['fieldtype'];
    $f->name = $spec['name'];
    $f->label = $spec['label'];
    $f->save();
    return $f;
  }
/**
 * Make a template
 *
 * @param string $key name of config input field
 * @param array $spec [array $t_parents [string Template name], array $t_children [string Template name], $array T_field $array [string Field name]]
 * @return object The new template
 */
  protected function makeTemplate($spec) {

    $fg = new Fieldgroup();
    $fg->name = $spec['name'];
    $fg->add($this->fields->get('title'));
    if(array_key_exists('t_fields', $spec)) {
      foreach ($spec['t_fields'] as $fieldname) {
        $fg->add($fieldname);
      }
    }
    $fg->save();

    $t = new Template();
    $t->name = $spec['name'];
    $t->fieldgroup = $fg;
    $t->save();
    return $t;
  }
/**
 * Apply family settings to template to restrict permitted parent and child templates
 *
 * @param string $key name of config input field
 * @param array $spec [array $t_parents [string Template name], array $t_children [string Template name], $array T_field $array [string Field name]]
 * @return boolean
 */
  protected function setTemplateFamily($spec) {

    $t = $this->templates->get($spec['name']);
    if(! $t->id) {
      $this->error("Unable to set family for template " . $spec['name'], Notice::logOnly);
      return false;
    }

    if(array_key_exists('t_parents', $spec)) {
      $parent_template_names = array();
      foreach ($spec['t_parents'] as $name) {
        $parent_template_names[] = $name;
      }
      // Set permitted parent templates
      $t->parentTemplates($parent_template_names);
    }

    if(array_key_exists('t_children', $spec)) {
      $child_template_names = array();
      foreach ($spec['t_children'] as $name) {
        $child_template_names[] = $name;
      }
      // Set permitted parent templates
      $t->childTemplates($child_template_names);
    }
    $t->save();
    return true;
  }
/**
 * Create a new page
 *
 * @param array $spec [string 'template' - name of template, string 'parent' - path of parent page, string 'title', string 'name']
 * @return Object The new page
 */
  public function makePage($spec) {

    $p = $this->wire(new Page());
    $p->template = $spec['template']; 
    $p->parent = $spec['parent'];
    $p->name = $spec['name'];
    $p->title = $spec['title'];
    $p->save();

    return $p;
  } 
/**
 * !!!!! Remove all created pages and associated fields, fieldgroups and templates - this is not currently called on uninstall 
 * 
 * Assumes it''s safe to remove this data
 *  
 * @param Boolean $recursive should child pages also be deleted?
 * @param Boolean $report_pg_errs should unfound pages trigger error?
 * @return String error message if there are live pages else Boolean success depending on whether all expected items can be found 
 */
  public function removePages($recursive=false, $report_pg_errs=true) {

    $setup = $this->setup;
    $errors = array();

    if(count($errors)) {
      $this->error("The following pages were not be deleted as they could not be found: " . implode(', ', $errors));
      $errors = array();
    }

    // Sort page array according to path depth so we're deleting children first
    uasort($setup["pages"], array($this, "cmpNumURLsegs"));

    foreach ($setup["pages"] as $p => $spec) {

      $p_path = $spec["parent"] . $p;
      $curr_p = $this->pages->get($p_path);

      if($curr_p->id){
        $curr_p->delete($recursive);
      } else if($report_pg_errs) {
        $errors[] = $spec["title"];
      }
    }
    if(count($errors)) {
      $this->error("The following pages could not be deleted: " . implode(', ', $errors));
      $errors = array();
    }
    foreach ($setup['templates'] as $t => $spec) {
      
      $curr_t = $this->templates->get($t);
      if( $curr_t !== null) {
        $rm_fldgrp = $curr_t->fieldgroup;
        wire('templates')->delete($curr_t);
        wire('fieldgroups')->delete($rm_fldgrp);  
      } else {
        $errors[] = $t;
      }
    }
    if(count($errors)) {
      $this->error("The following templates could not be deleted: " . implode(', ', $errors));
      $errors = array();
    }
    foreach($setup['fields'] as $f => $spec) {
      $curr_f = wire('fields')->get($f);
      if($curr_f !== null) {
        wire('fields')->delete($curr_f);
      } else {
        $errors[] = $f;
      }
    }
    if(count($errors)) {
      $this->error("The following fields were not be deleted as they could not be found: " . implode(', ', $errors));
    }
  }
/**
 * Compare the number of two url segments
 *  
 * @param String $a
 * @param String $b
 */
  protected function cmpNumURLsegs($a, $b) {

    $a_count = substr_count($a["parent"], "/");
    $b_count = substr_count($b["parent"], "/");

    if ($a_count === $b_count) {
        return 0;
    }
    return ($a_count > $b_count) ? -1 : 1;
  }
  public function customUninstall($event) {
    $class = $event->arguments(0);
    if($class !== $this->className) return;
    bd('customUninstall');
    bd($event->return);
    bd(gettype($this->rmv_created));

    if($this->rmv_created === 1) {

      $pages_removed = $this->removePages();

    }
  }
}