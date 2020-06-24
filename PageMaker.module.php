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
     $this->addHookBefore("Modules::uninstall", $this, "customUninstall");
  }
/**
 * Check there are no naming collisions before completing installation
 *
 * @param array $setup Array of elements to check ['fields' Array of strings , 'templates' Array of strings]
 * @return array of errors or boolean true
 */
  public function preflightInstall($setup, $names) {

    $errors = array(
      'fields' => array(),
      'templates' => array()
    );

    // Check if fields exist
    foreach($setup['fields'] as $f => $spec) {
      $curr_f = wire('fields')->get($names[$f]);
      
      if($curr_f !== null) {
        $errors['fields'][] = $names[$f];
      }
    }

    foreach ($setup['templates'] as $t => $spec) {
      $curr_t = $this->templates->get($names[$t]);

      if( $curr_t !== null) {
        $errors['templates'][] = $names[$t];
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
  public function makePages($setup, $names) {

    $installable = $this->preflightInstall(array('fields' => $setup['fields'], 'templates' => $setup['templates']), $names);

    if($installable === true){

      foreach ($setup['fields'] as $key => $spec) {
        $spec['name'] = $this->nameFromHandle($key, $names, 'field');
        $this->makeField($spec);
      }

      $templates_spec = array(); // Save template spec for setTemplateFamily

      foreach ($setup['templates'] as $key => $spec) { 
        $spec['name'] = $this->nameFromHandle($key, $names, 'template');

        // Change field handles to actual field names
        foreach ($spec as $specitem => &$handles) {
          if($specitem !== 'name') {
            $el_type = substr($specitem, 2); // remove the first 2 chars

            // Replace handles with corresponding live names
            $named_handles = array();
            foreach ($handles as $h) { // Array of template handles to replace
              $named_handles[] = $this->nameFromHandle($h, $names, $el_type);
            }
            $handles = $named_handles;
          }
        }
        unset($handles);
        
        $templates_spec[$key] = $spec; 
        $this->makeTemplate($spec);
      }
      foreach ($templates_spec as $key => $spec) {

        $this->setTemplateFamily($spec);
       
      }
      foreach ($setup['pages'] as $key => $spec) {
        $spec['template'] = $this->nameFromHandle($spec['template'], $names, 'template');
        $spec['name'] = $key;
        $this->makePage($spec);
      }
      // Store $setup
      $data = $this->modules->getConfig($this->className);
      $data['setup'] = $setup;
      $data['names'] = $names;
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

    $spec_type = gettype($spec);

    bd("spec is $spec_type");
    
    $p = $this->wire(new Page());
    $p->template = $spec['template']; 
    $p->parent = $spec['parent'];
    $p->name = $spec['name'];
    $p->title = $spec['title'];
    $p->save();

    return $p;
  }
/**
 * Get live name from handle
 *
 * @param String $handle The name of a variable storing the name of an element we're creating
 * @param Array $names The array of handle => name pairs
 * @return String $elmt The element to name
 */
  public function nameFromHandle($handle, $names, $elmt) {
    $name = $names[$handle];
    if(is_null($name)){
      throw new WireException(__LINE__ . ": Unable to create $elmt as name was not provided");
    }
    return $name;
  }  
/**
 * Remove all created pages and associated fields, fieldgroups and templates 
 * 
 * @return String error message if there are live pages else Boolean success depending on whether all expected items can be found 
 */
  public function removePages($fromSelf=false) {
    $data = $this->modules->getConfig($this->className);
    $setup = $data['setup'];
    $names = $data['names']; 
    $abort = false;

    if($this->preflightUninstall($setup['pages'])) {

      $errors = array();

      if(count($errors)) {
        $this->error("The following pages were not be deleted as they could not be found: " . implode(', ', $errors));
        $errors = array();
      }
      // Don't need to woory about pages as they've been removed already

      foreach ($setup['templates'] as $t => $spec) {
        // $this doesn't get us the config from ProcessInstallPages
        $curr_t = $this->templates->get($names[$t]);
        if( $curr_t !== null) {
          $rm_fldgrp = $curr_t->fieldgroup;
          wire('templates')->delete($curr_t);
          wire('fieldgroups')->delete($rm_fldgrp);  
        } else {
          $abort = true;
          $errors[] = $names[$t];
        }
      }
      if(count($errors)) {
        $this->error("The following templates could not be deleted: " . implode(', ', $errors));
        $errors = array();
      }
      foreach($setup['fields'] as $f => $spec) {
        $curr_f = wire('fields')->get($names[$f]);
        if($curr_f !== null) {
          wire('fields')->delete($curr_f);
        } else {
          $abort = true;
          $errors[] = $names[$f];
        }
      }
      if(count($errors)) {
        $this->error("The following fields were not be deleted as they could not be found: " . implode(', ', $errors));
      }
    } else {
      // Failed preflight - there are live orders
      $abort = true;
      $m_config = $this->modules->getConfig($this->className);
      $err_mssg = $m_config['err_mssg'];

      if($fromSelf) {
        // Going with an exception for now - will test more measured approach if I use the module stand alone
        throw new WireException($err_mssg);
        // $this->error($err_mssg);
      } else {
        // Let calling module show the error message
        return($err_mssg);
      }
    }
    if($abort) {
      return false;
    }
    return true; 
  }
  public function customUninstall($event) {
    $class = $event->arguments(0);
    if($class !== $this->className) return;

    $pages_removed = $this->removePages(true);

    if( ! $pages_removed) {
      $event->replace = true; // prevent uninstall
      $this->session->redirect("./edit?name=$class"); // prevent "module uninstalled" message
    }
  }
/**
 * Check it's safe to delete provided pages
 *
 * @param array $ps Names of pages to check
 * @return boolean true if pages are safe to delete
 */
  protected function preflightUninstall($ps) {

    // Check for ongoing orders
    foreach ($ps as $pg => $spec) {
      $selector = 'name=' . $pg;
      $curr_p = $this->pages->findOne($selector);
      if($curr_p->id !== 0){
        return false;
      }
      if($curr_p->numChildren()) {
        return false;
      }
    }
    return true;
  }
}