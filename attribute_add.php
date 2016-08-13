<!DOCTYPE html>
<html lang="en">
	<head>
		<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.1/css/bootstrap-combined.min.css" rel="stylesheet">

		<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
		<!--[if lt IE 9]>
			<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->

		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
		<script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.1/js/bootstrap.min.js"></script>
	</head>

	<body>

<?php
use PFBC\Form;
use PFBC\Element;
use PFBC\Validation;

session_start();
error_reporting(E_ALL);
include("PFBC/Form.php");

if(isset($_POST["form"])) {
	if(Form::isValid("addattribute"))
	{
	    print_r($_POST);
	    exit();
	}
         
	header("Location: " . $_SERVER["PHP_SELF"]);
	exit();	
}

$form = new Form("addattribute");
$form->configure(array(
	"prevent" => array("bootstrap", "jQuery")
));
$form->addElement(new Element\Hidden("form", "addattribute"));

$form->addElement(new Element\HTML('<legend>Attribute Description</legend>'));

$form->addElement(new Element\Select(
  "Applies to Asset Type", 
  "type",
  Array(
    "system" => "Information System" , 
    "asset" => "Information Asset",
  ),
  Array(
    "longdesc" => "Information Assets are datasets. Information Systems store and process data",
    "required" => 1,
    "validation" => new Validation\RegExp("/^system$|^asset$/", "Asset type must be Information System or Information Asset"),
  )
));

$form->addElement(new Element\Number(
  "Display Priority [0,9]",
  "display_priority",  
  Array(
    "longdesc" => "Smaller numbers displayed closer to top.", 
    "value" => "4",
    "required" => 1,
    "validation" => new Validation\RegExp("/^[0-9]$/", "Display Priority is an integer between 0 and 9 inclusive"),
  )
));

$form->addElement(new Element\Textbox(
  "Attribute Body", 
  "body",
  Array(
    "longdesc" => "Main text of the attribute.  Must be a statement that has a true/false answer.",
    "required" => 1, 
    "validation" => new Validation\RegExp("/[a-zA-Z0-9]/", "Body must contain something besides whitespace"),
  )
));

$form->addElement(new Element\Textbox(
  "Verbose Description", 
  "verbose",
  Array(
    "longdesc" => "A verbose description of the attribute, shown under the body.",
    "required" => 1, 
    "validation" => new Validation\RegExp("/[a-zA-Z0-9]/", "Verbose Description must contain something besides whitespace"),
  )
));

$form->addElement(new Element\Textbox(
  "Rationale", 
  "rationale",
  Array(
    "longdesc" => "A rationale for the relevancy of this attribute.  Shown when user expands the discription.",
    "required" => 1, 
    "validation" => new Validation\RegExp("/[a-zA-Z0-9]/", "Rationale must contain something besides whitespace"),
  )
));

// Loss potential
$form->addElement(new Element\HTML('<legend>Loss Potentials (unmitigated)</legend>'));

$lp_values = Array(
    "0" => "Low - Negligible or N/A.",
    "1" => "Medium - Well, that's annoying.",
    "2" => "High - Beep-beep, here comes the bus!",
);


$form->addElement(new Element\Select(
  "Disclosure Loss Potential", 
  "loss_disclosure",
  $lp_values,
  Array(
    "longdesc" => "Loss potential presented by this attribute due to unauthorized disclosure (failure to maintain confidentiality of asset).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Disclosure Loss Potential must be low, medium, or high."),
  )
));

$form->addElement(new Element\Select(
  "Disruption Loss Potential", 
  "loss_disruption",
  $lp_values,
  Array(
    "longdesc" => "Loss potential presented by this attribute due to disruption (failure to maintain availability of asset).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Disruption Loss Potential must be low, medium, or high."),
  )
));

$form->addElement(new Element\Select(
  "Usurpation Loss Potential", 
  "loss_usurpation",
  $lp_values,
  Array(
    "longdesc" => "Loss potential presented by this attribute due to usurpation (failure to maintain integrity due to unauthorized control of asset).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Usurpation Loss Potential must be low, medium, or high."),
  )
));

$form->addElement(new Element\Select(
  "Impersonation Loss Potential", 
  "loss_impersonation",
  $lp_values,
  Array(
    "longdesc" => "Loss potential presented by this attribute due to impersonation (failure to maintain integrity due to replacement of asset with a falsified one).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Impersonation Loss Potential must be low, medium, or high."),
  )
));


// Loss Frequency

$form->addElement(new Element\HTML('<legend>Frequency of Attacks (successful and unsuccessful)</legend>'));

$lf_values = Array(
    "0" => "Rare - N/A or More likely to win the lottery.",
    "1" => "Often - A few times a year.",
    "2" => "Frequent - Daily.",
);


$form->addElement(new Element\Select(
  "Frequency of attacks causing disclosure", 
  "prob_disclosure",
  $lf_values,
  Array(
    "longdesc" => "Frequency of successful and unsuccessful attacks presented by this attribute, intended to cause unauthorized disclosure (failure to maintain confidentiality of asset).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Frequency of disclosure must be rare, often, or frequent."),
  )
));

$form->addElement(new Element\Select(
  "Frequency of attacks causing disruption", 
  "prob_disruption",
  $lf_values,
  Array(
    "longdesc" => "Frequency of successful and unsuccessful attacks presented by this attribute, intended to cause disruption (failure to maintain availability of asset).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Frequency of disruption must be rare, often, or frequent."),
  )
));

$form->addElement(new Element\Select(
  "Frequency of attacks causing usurpation", 
  "prob_usurpation",
  $lf_values,
  Array(
    "longdesc" => "Frequency of successful and unsuccessful attacks presented by this attribute, intended to cause usurpation (failure to maintain integrity due to unauthorized control of asset).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Frequency of usurpation must be rare, often, or frequent."),
  )
));

$form->addElement(new Element\Select(
  "Frequency of attacks causing impersonation", 
  "prob_impersonation",
  $lf_values,
  Array(
    "longdesc" => "Frequency of successful and unsuccessful attacks presented by this attribute, intended to cause impersonation (failure to maintain integrity due to replacement of asset with a falsified one).",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Frequency of impersonation must be rare, often, or frequent."),
  )
));




// Risk Mitigation

$form->addElement(new Element\HTML('<legend>Attribute Risk Mitigation Effectiveness</legend>'));

$rp_values = Array(
  "2" => "Super Effective",
  "1" => "Adequate",
  "0" => "No Effect",
);


$form->addElement(new Element\Select(
  "Effectivness against disclosure", 
  "mitigation_disclosure",
  $rp_values,
  Array(
    "longdesc" => "Ability to stop attacks intended to cause unauthorized disclosure.",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Effectiveness against disclosure must be super effective, adequate, or no effect."),
  )
));

$form->addElement(new Element\Select(
  "Effectivness against disruption", 
  "mitigation_disruption",
  $rp_values,
  Array(
    "longdesc" => "Ability to stop attacks intended to cause disruption.",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Effectiveness against disruption must be super effective, adequate, or no effect."),
  )
));

$form->addElement(new Element\Select(
  "Effectivness against usurpation", 
  "mitigation_usurpation",
  $rp_values,
  Array(
    "longdesc" => "Ability to stop attacks intended to cause usurpation.",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Effectiveness against usurpation must be super effective, adequate, or no effect."),
  )
));

$form->addElement(new Element\Select(
  "Effectivness against impersonation", 
  "mitigation_impersonation",
  $rp_values,
  Array(
    "longdesc" => "Ability to stop attacks intended to cause impersonation.",
    "required" => 1, 
    "value" => 0,
    "validation" => new Validation\RegExp("/^[012]$/", "Effectiveness against impersonation must be super effective, adequate, or no effect."),
  )
));



    

$form->addElement(new Element\Button);
$form->render();
?>

	</body>
</html>
