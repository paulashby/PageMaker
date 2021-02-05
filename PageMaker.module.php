<?php namespace ProcessWire;
class PageMaker extends WireData implements Module {
  public static function getModuleInfo() {
    return [
      "title" => "Page Maker",
      "summary" => "Provides the ability to create and manage a set of pages",
      "author" => "Paul Ashby, primitive.co",
      "version" => 1.1,
      "singular" => true,
      'autoload' => true
    ];
  }

  public function init() {

    $this->addHookBefore("Modules::uninstall", $this, "customUninstall");
  }
/**
 * Check there are no naming collisions before making pages
 *
 * @param Array $setup Array of elements to check ["fields" Array of strings , "templates" Array of strings]
 * @return Array of errors or boolean true
 */
  protected function preflightMakePages($setup) {

    $errors = array(
      "page_set" => array(),
      "pages" => array(),
      "fields" => array(),
      "templates" => array()
    );

    // check it's OK to replace pre-existing page_set
    if($this->retainExisting($setup)){
        $errors["page_set"][] = $setup["set_name"];
    }

    // Check we're not overwriting existing pages
    foreach($setup["pages"] as $p => $spec) {
      $page_path = $spec["parent"] . $p;
      $curr_p = wire("pages")->get($page_path);
      if($curr_p->id) {
        $errors["pages"][] = $p;
      }
    }

    // Check we're not overwriting existing fields or templates
    $errors["fields"] = $this->checkPageElement("fields", $setup["fields"]);
    $errors["templates"] = $this->checkPageElement("templates", $setup["templates"]);

    // return $errors;
    $mssgs = array();
    foreach ($errors as $elmts => $elnames) {
      if(count($elnames)){
        $mssgs[] = "The following $elmts could not be created as they already exist: " .implode(", ", $elnames);
      }
    }
    if(count($mssgs)) {
      return $mssgs; 
    }
    return true;
  }
/**
 * Check whether we can overwrite a existing set with the same name
 *
 * @param Array $setup Spec for proposed set
 * @return Boolean
 */
  protected function retainExisting($setup) {
    // Replace_existing is false and a page_set called $setup["set_name name"] already exists
    return $setup["replace_existing"] !== true && isset($this["page_sets"]) && isset($this["page_sets"][$setup["set_name"]]);
  }
/** 
 * Check whether field or template exists
 *
 * @param String $elmt_type Name of element type (plural)
 * @param Array $setup Spec for elements of given type
 * @return Boolean
 */
  protected function checkPageElement($elmt_type, $setup) {

    $errors = array();

    // Check we're not overwriting existing fields
    foreach($setup as $elmt => $spec) {
      $curr_elmt = wire($elmt_type)->get($elmt);
      
      if($curr_elmt !== null) {
        $errors[] = $elmt;
      }
    }
    return $errors;
  }
/**
 * Create set of pages
 *
 * @param String $set_name
 * @param Array $setup Array of elements to create [
 *        "fields" [field_name => ["fieldtype", string "label"]] , 
 *        "templates" [template_name =>["t_children"=>[Strings], "t_parents"=>[Strings], "t_fields"=>[Strings]]]
 *        "pages" [page_name => ["template" => String, "parent" => String (path of page), "title" => String]]
 * @param Boolean $replace_existing - if true, existing page_sets with the same $set_name with be replaced
 * @param Boolean $survive_uninstall - if true, page sets will be left in place on uninstall, regardless of the value of rmv_created
 * @return Array of errors or boolean true
 */
  public function makePages($set_name, $setup, $replace_existing = false, $survive_uninstall = false) {

    $makeable = $this->preflightMakePages(array(
      "set_name" => $set_name,
      "pages" => $setup["pages"], 
      "fields" => $setup["fields"], 
      "templates" => $setup["templates"],
      "replace_existing" => $replace_existing
    ));

    if($makeable === true){

      foreach ($setup["fields"] as $key => $spec) {
        $spec["name"] = $key;//$pec is name, fieldtype and label
        $this->makeField($spec);
      }
      foreach ($setup["templates"] as $key => $spec) { 
        $spec["name"] = $key;
        $this->makeTemplate($spec);
      }
      if($this->config->version("3.0.153")) {
        foreach ($setup["templates"] as $key => $spec) {
          $spec["name"] = $key;
          $this->setTemplateFamily($spec);
        }
      }
      foreach ($setup["pages"] as $key => $spec) {
        $spec["name"] = $key;
        $this->makePage($spec);
      }

      // Store $setup
      $data = $this->modules->getConfig($this->className);

      if( ! array_key_exists("page_sets", $data)){
        $data["page_sets"] = array();
      }
      $data["page_sets"][$set_name] = ["setup" => $setup, "survive_uninstall" => $survive_uninstall];
      $this->modules->saveConfig($this->className, $data);

      return true;

    } else {
      // preflightMakePages will return array of errors: ("fields"=>[], "templates")
      return $makeable;
    }
  }
/**
 * Make a field
 *
 * @param Array $spec [string "name", string "fieldtype", string "label"]
 * @return Object The new field
 */
  protected function makeField($spec) {

    $f = $this->fields->get($spec["name"]);

    if(is_null($f)) {

      $f = new Field();
      $f->type = $spec["fieldtype"];
      $f->name = $spec["name"];
      $f->label = $spec["label"];
      $f->save();
    }
    return $f;
  }
/**
 * Make a template
 *
 * @param Array $spec [string "name", array "t_parents" [string Template name], array "t_children" [string Template name], array "t_fields" [string Field name]]
 * @return Object The new template
 */
  protected function makeTemplate($spec) {

    $fg = new Fieldgroup();
    $fg->name = $spec["name"];
    $fg->add($this->fields->get("title"));
    if(array_key_exists("t_fields", $spec)) {
      foreach ($spec["t_fields"] as $fieldname) {
        $fg->add($fieldname);
      }
    }
    $fg->save();

    $t = new Template();
    $t->name = $spec["name"];
    $t->fieldgroup = $fg;
    if(array_key_exists("t_access", $spec)) {
      // t_access array contains one or more of the following role arrays: "view", "edit", "create", "add"
      $t->useRoles = 1;
      foreach ($spec["t_access"] as $access_type => $roles) {
        $t->setRoles($roles, $access_type);
      }
    }
    $t->save();
    return $t;
  }
/**
 * Create a new page
 *
 * @param Array $spec [string "template" - name of template, string "parent" - path of parent page, string "title", string "name"]
 * @return Object The new page
 */
  public function makePage($spec) {

    $p = $this->wire(new Page());
    $p->template = $spec["template"]; 
    $p->parent = $spec["parent"];
    $p->name = $spec["name"];
    $p->title = $spec["title"];
    $p->save();

    return $p;
  } 
/**
 * Apply family settings to template to restrict permitted parent and child templates
 *
 * @param Array $spec [string "name", array "t_parents" [string Template name], array "t_children "[string Template name], array "t_fields" [string Field name]]
 * @return Boolean
 */
  protected function setTemplateFamily($spec) {

    $t = $this->templates->get($spec["name"]);
    if(! $t->id) {
      $this->error("Unable to set family for template " . $spec["name"], Notice::logOnly);
      return false;
    }

    if(array_key_exists("t_parents", $spec)) {
      $parent_template_names = array();
      foreach ($spec["t_parents"] as $name) {
        $parent_template_names[] = $name;
      }
      // Set permitted parent templates
      $t->parentTemplates($parent_template_names);
    }

    if(array_key_exists("t_children", $spec)) {
      $child_template_names = array();
      foreach ($spec["t_children"] as $name) {
        $child_template_names[] = $name;
      }
      // Set permitted parent templates
      $t->childTemplates($child_template_names);
    }
    $t->save();
    return true;
  }
/**
 * Remove all created pages and associated fields, fieldgroups and templates - this is called on uninstall only if rmv_created is checked
 * 
 * Assumes it's safe to remove this data
 * Does not delete page sets whose survive_uninstall value is true
 *  
 * @param Boolean $report_pg_errs Should unfound pages or templates/fields repurposed for user-created pages trigger error?
 * @return String Error message if there are live pages else Boolean success depending on whether all expected items can be found 
 */
  public function removeAll($report_pg_errs = true, $uninstalling = false) {

    $page_sets = $this->page_sets;

    if(is_null($page_sets)) return;

    foreach ($page_sets as $page_set => $spec) {
      
      if($uninstalling && $spec["survive_uninstall"]) {
        continue;
      }
      $this->removeSet($page_set, $report_pg_errs);
    }
  }
/**
 * Remove set of pages and associated fields, fieldgroups and templates
 * 
 * Assumes it's safe to remove this data (inlcuding any child pages that may have been added)
 *  
 * @param String $page_set The page set to remove 
 * @param Boolean $report_pg_errs Should unfound pages or templates/fields repurposed for user-created pages trigger error?
 * @return String error message if there are live pages else Boolean success depending on whether all expected items can be found 
 */
  public function removeSet($page_set, $report_pg_errs = true) {
    
    $setup = $this["page_sets"][$page_set]["setup"];

    // Sort page array according to path depth so we're deleting children first
    uasort($setup["pages"], array($this, "cmpNumURLsegs"));

    $this->removePages($setup["pages"], $report_pg_errs);
    $this->removeTemplates($setup["templates"]);
    $this->removeFields($setup["fields"]);

    // Remove page_set record
    $data = $this->modules->getConfig($this->className);
    unset($data["page_sets"][$page_set]);
    $this->modules->saveConfig($this->className, $data);
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
/**
 * !!!!! Remove given pages
 * 
 * Assumes it's safe to remove this data (inlcuding any child pages that may have been added)
 *  
 * @param String $pages The pages to remove 
 * @param Boolean $report_pg_errs Should unfound pages or templates/fields repurposed for user-created pages trigger error?
 */
  protected function removePages($pages, $report_pg_errs) {

    $errors = array();

    // Delete pages in given set
    foreach ($pages as $p => $spec) {

      $p_path = $spec["parent"] . $p;
      $curr_p = $this->pages->get($p_path);

      if($curr_p->id){
        // Delete page and children
        $curr_p->delete(true);
      } else if($report_pg_errs) {
        $errors[] = $spec["title"];
      }
    }
    if(count($errors)) {
      $this->error("The following pages could not be removed as they could not be found: " . implode(", ", $errors));
    }
  }
/**
 * Remove given templates
 *   
 * @param String $templates The templates to remove 
 */
  protected function removeTemplates($templates) {

    $errors = array();
    $keep = array();

    // Remove unused templates. Show error for any still in use
    foreach ($templates as $t => $spec) {
      
      $curr_t = $this->templates->get($t);
      
      if( $curr_t !== null) {

        $in_use = count(wire("pages")->find("template={$t}"));

        // Make sure template hasn't been used on any other pages
        if($in_use){

          // Template is in use even though we've deleted all the pages in the set
          if( ! in_array($t, $keep)){
            $keep[] = $t;
          }

        } else {
          $rm_fldgrp = $curr_t->fieldgroup;
          wire("templates")->delete($curr_t);
          wire("fieldgroups")->delete($rm_fldgrp);
        } 
      } else {
        $errors[] = $t;
      }
    }
    if(count($errors)) {
      // Report unfound templates
      $this->error("The following templates could not be removed as they could not be found: " . implode(", ", $errors));
      $errors = array();
    }

    // Report templates in use by pages other than this set
    if(count($keep)){
      $this->error("The following templates are still in use and cannot be removed: " . implode(", ", $keep));
    }
  }
/**
 * Remove given fields
 *  
 * @param String $fields The fields to remove 
 */
  protected function removeFields($fields) {

    $errors = array();

    foreach($fields as $f => $spec) {

      $curr_f = wire("fields")->get($f);

      if($curr_f !== null) {

        $f_templates = $curr_f->getTemplates();
        $in_use = $f_templates->count();

        if($in_use){

          $used_on = array();
          foreach ($f_templates as $key => $template) {
            $used_on[] = $template->name;
          }
          // Report that field is in use by remaining templates
          $this->error("The {$curr_f->name} field could not be removed as it is in use on the following templates: " . implode(", ", $used_on));
        } else {
          wire("fields")->delete($curr_f);
        }
      } else {
        $errors[] = $f;
      }
    }
    if(count($errors)) {
      // Report unfound fields
      $this->error("The following fields were not be deleted as they could not be found: " . implode(", ", $errors));
    } 
  }
  public function customUninstall($event) {
    
    $class = $event->arguments(0);
    if($class !== $this->className) return;
    
    if($this->rmv_created === 1) {
      $pages_removed = $this->removeAll(true, true);
    }
  }
}