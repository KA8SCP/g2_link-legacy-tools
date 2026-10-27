<?php
require __DIR__.'/../scripts/check-php-security.php';
$checks=0;
function verifySecurity($condition,$name) { global $checks; ++$checks; if (!$condition) { fwrite(STDERR,"FAIL $name\n"); exit(1); } }
foreach (array(
    array('5.4.16','48.el7','CentOS',false),
    array('5.4.45','19.el7','GetPageSpeed LLC',false),
    array('5.4.45','20.el7','GetPageSpeed LLC',true),
    array('5.4.45','23.el7','GetPageSpeed LLC',true),
    array('5.4.45','23.el7','Unknown',false),
    array('8.0.30','8.el9_8','AlmaLinux',false),
    array('8.0.30','8.el9_8.1','AlmaLinux',true),
    array('8.0.30','8.el9_8.alma.1','AlmaLinux',true),
    array('8.0.30','8.el9_8.1','Unknown',false),
    array('8.2.33','1.module_el9.8.0+303+1bef6c12','AlmaLinux',false),
    array('8.2.35','1.module_el9.8.0+329+e5668d75','AlmaLinux',true),
    array('8.3.35','1','Vendor',false),array('8.3.36','1','Vendor',true),
    array('8.4.26','1','Vendor',false),array('8.4.27','1','Vendor',true),
    array('8.5.11','1','Vendor',false),array('8.5.12','1','Vendor',true),
    array('8.6.0','1','Vendor',false)
) as $row) verifySecurity(dashboardPhpPackageFixed($row[0],$row[1],$row[2]) === $row[3],implode(' ',array_slice($row,0,3)));
$inventory="php-cli\t5.4.45\t23.el7\tGetPageSpeed LLC\nphp-common\t5.4.45\t23.el7\tGetPageSpeed LLC\nphp\t5.4.45\t23.el7\tGetPageSpeed LLC\n";
verifySecurity(count(dashboardPhpSecurityInventory($inventory,'5.4.45'))===3,'patched mod_php inventory');
foreach (array(
    str_replace("php\t5.4.45", "php\t5.4.16",$inventory),
    $inventory.$inventory,
    $inventory."php82-php-fpm\t8.2.35\t1\tVendor\n",
    str_replace("php-cli\t5.4.45\t23.el7", "php-cli\t5.4.45\t24.el7",$inventory),
    "invalid inventory",
    "php-cli\t5.4.45\t23.el7\tGetPageSpeed LLC\n"
) as $bad) {
    $rejected=false; try { dashboardPhpSecurityInventory($bad,'5.4.45'); } catch (Exception $e) { $rejected=true; }
    verifySecurity($rejected,'mixed/missing/unknown inventory rejected');
}
$rejected=false; try { dashboardPhpSecurityInventory($inventory,'8.4.27'); } catch (Exception $e) { $rejected=true; }
verifySecurity($rejected,'CLI mismatch rejected');
echo "PHP security prerequisite: $checks checks passed.\n";
