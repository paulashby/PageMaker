# PageMaker

[<img src="https://img.shields.io/badge/License-MIT-yellow.svg">](https://opensource.org/licenses/MIT)

## Table of Contents

[Description](#description)<br />[Installation](#installation)<br />[Usage](#usage)<br />[Contributing](#contributing)<br />[Tests](#tests)<br />[License](#license)<br />[Questions](#questions)<br />

## Description

Module for the [Processwire](https://processwire.com) Content Management System to simplify the process of creating multiple interdependent pages, templates and fields.

## Installation

Firstly, download and install the latest version of [Processwire](https://processwire.com). Download the PageMaker folder and place in your /site/modules directory.<br /><br />Log into your site as an admin and go the Modules page. Select the Site tab and click the Install button on the PageMaker module entry.<br /><br />By default, the module will leave all created fields, templates and pages in place when it is uninstalled. If you'd prefer to remove all created items on uninstall, go to the module settings page and check the "Remove pages on uninstall" checkbox.

## Usage

Load the module in your php file<br />
```$page_maker = $this->modules->get("PageMaker");```<br />

Create a named set of pages<br />
```$page_maker->makePages("my_pages", $setup, true, true);```<br />

Parameters for the makePages method:
- **set_name** - string: the name of the page set
- **setup** - associative array: the specification of field, template and page configurations. <br />
  Each of these is itself an associative array:<br />
    ```
    $pgs = array(
      "fields" => array(
        "customer" => array("fieldtype"=>"FieldtypeText", "label"=>"Customer", "config"=>array("html_ee")),
        "sku_ref" => array("fieldtype"=>"FieldtypeText", "label"=>"Record of cart item sku", "config"=>array("html_ee")),
        "quantity" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Number of units"),
        "purchase_price" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Price when ordered"),
        "discount" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Discount applicable at time of order"),
        "ecopack" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Supply in eco pack")
      ),
      "templates" => array(
        "line-item" => array("t_parents" => array("cart-item", "order"), "t_fields"=>array("customer", "sku_ref", "quantity","purchase_price", "discount", "ecopack")),
        "cart-item" => array("t_parents" => array("section"), "t_children" => array("line-item"))
      ),
      "pages" => array(
        "cart-items" => array("template" => "cart-item", "parent"=>"{$order_root_path}order-pages/", "title"=>"Cart Items")
      )
    );
    ```

    The arrays are processed in order - fields are handled first and can then be added to templates which in turn can be added to pages.<br />
    #### **The *fields* array**
    Associative array containing details of every field required for the page templates you're creating. Key is the name of the new field, value an associative array providing the following:
    - **fieldtype** - (required) the [Processwire fieldtype](https://processwire.com/api/ref/fieldtypes/)<br />
    - **label** - (required) the label that will appear alongside the field in the backend<br />
    - **config** - an array of additional configuration strings for the field:<br />
      - **"ck_editor"** - set Inputfield Type to CKEditor
      - **"html_ee"** - set Text Formatter to HTML Entity Encoder
      - **"markup"** - set Content Type to Markup/HTML
      - **"image"** - set accepted file types to gif, jpeg or png<br />
    #### **The *templates* array**
    Associative array containing details of every template required for the pages you're creating. Key is the name of the new template, value an associative array providing the following:
    - **t_fields** - an array of field name strings - these will be added to the template<br />
    - **t_parents** - an array of template name strings - only those listed will be permitted as parents of the new template<br />
    - **t_children** - an array of template name strings - only those listed will be permitted as children of the new template<br />
    #### **The *pages* array**
    Associative array containing details of every page you're creating. Key is the name of the new page (which will be used internally by Processwire), value an associative array providing the following:
    - **template** - string: the name of the template to use for the new page<br />
    - **path** - string: the location of the new page's parent<br />
    - **title** - string: the title of the new page - this can be upper and lower case and may include spaces<br /><br />

- **replace_existing** - boolean: overwrite existing set with same name.<br />
- **survive_uninstall** - boolean: leave in place when module is uninstalled, regardless of the module settings.<br />
 
  
## Contributing

If you would like to make a contribution to the app, simply fork the repository and submit a Pull Request. If I like it, I may include it in the codebase.

## Tests

N/A

## License

Released under the [MIT](https://opensource.org/licenses/MIT) license.

## Questions

Feel free to [email me](mailto:paul@primitive.co?subject=PageMaker%20query%20from%20GitHub) with any queries.
