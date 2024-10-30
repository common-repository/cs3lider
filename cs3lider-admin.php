<?php declare (strict_types = 1);

namespace cs3lider;

require_once 'exptrust/prelude.php';
require_once 'exptrust/Either.php';

const SLIDER_DEFAULT = ['slides' => [], 'interval' => 5000];


add_action ('plugins_loaded', function () {


add_action ('admin_enqueue_scripts', function () {
    wp_enqueue_style ('cs3lider-admin', plugin_dir_url (__FILE__) . 'css/cs3lider-admin.css');
    wp_enqueue_script ('cs3lider-admin', plugin_dir_url (__FILE__) . 'js/cs3lider-admin.js', ['jquery']);
    wp_localize_script ('cs3lider-admin', 'CS3liderAdmin', ['ajax' => admin_url ('admin-ajax.php') . '?action=cs3lider_',
                                                            'nonce' => wp_create_nonce ('cs3lider-private-admin')]);
}, 9);

add_action ('admin_menu', function () { add_menu_page ('CS^3lider', 'CS^3lider', 'manage_options', 'cs3lider-settings', function () {
?>
<form id="cs3lider-form" action="" method="POST">
    <h2>Usage</h2>
    <ul>
        <li>Write the shortcode in your posts or pages like this: [cs3lider]</li>
        <li>Or, add the widget via Appearance -> Widgets.</li>
    </ul>
    <h2>Slides</h2>
    <ul id="cs3lider-admin-slides"></ul>
    <input type="file" name="cs3lider-slide-image" id="cs3lider-slide-image" value="" accept=".png,.jpg,.jpeg" /><br />
    <input type="button" name="cs3lider-add-slide" id="cs3lider-add-slide" value="Add new slide" class="button button-secondary" />
    <h2>Autoplay</h2>
    <table class="form-table">
        <tr>
            <th>Interval</th>
            <td><label><input type="text" id="cs3lider-interval">(ms)</label></td>
        </tr>
    </table>
    <input type="button" name="cs3lider-save-slider" id="cs3lider-save-slider" class="button button-primary" value="Save Slider" />
    <div id="cs3lider-message"></div>
</form>
<?php
}); });

function add_ajax_action (string $name, callable $action) {
    add_action ('wp_ajax_cs3lider_' . $name, function () use ($action) {
        header ('Content-type: application/json');
        $action () -> cata (function ($v) { return function () use ($v) { header ('Status: ' . $v['status']); echo json_encode ($v); }; },
                            function ($v) { return function () use ($v) { echo json_encode ($v); }; } ) ();
        exit;
    });
}

add_ajax_action ('get_slider', function () : \exptrust\Either { return \exptrust\right() (get_option ('cs3lider_slider', SLIDER_DEFAULT)); } );

add_ajax_action ('save_slider', function () : \exptrust\Either {
    $json = json_decode (file_get_contents ("php://input"), true);
    if (!isset ($json['nonce']) || !wp_verify_nonce ($json['nonce'], 'cs3lider-private-admin'))
        return \exptrust\left() (['status' => 401, 'msg' => 'Invalid request.']);
    $sliderinfo = $json['sliderinfo'];
    if (count ($sliderinfo['slides']) < 1)
        return \exptrust\left() (['status' => 500, 'msg' => 'Slider needs at least one slide.']);
    foreach ($sliderinfo['slides'] as $slide)
        foreach (['img_path', 'alt', 'url'] as $field)
            $slide[$field] = sanitize_text_field ($slide[$field]);
    $sliderinfo['interval'] = sanitize_text_field ($sliderinfo['interval']);
    if (!filter_var($sliderinfo['interval'], FILTER_VALIDATE_INT) || $sliderinfo['interval'] <= 0)
        return \exptrust\left() (['status' => 500, 'msg' => 'Autoplay interval must be a positive integer.']);
    update_option ('cs3lider_slider', $sliderinfo);
    update_option ('cs3lider_css', css ($sliderinfo));
    update_option ('cs3lider_html', html ($sliderinfo));
    return \exptrust\right() (['msg' => 'Slider updated successfully.']);
} );

add_ajax_action ('add_image', function () : \exptrust\Either {
    if (!check_ajax_referer ('cs3lider-private-admin', 'file_nonce', false))
        return \exptrust\left() (['status' => 401, 'msg' => 'Invalid request.']);

    if (!isset ($_FILES))
        return \exptrust\left() (['status' => 500, 'msg' => 'Please select a file to upload.']);

    $name = $_FILES['file_data']['name'];
    preg_match ("/.(" . implode ( "|", [ 'jpg', 'png', 'jpeg' ] ) . ")$/i", $name, $extstatus_matches);
    if (!in_array ($_FILES['file_data']['type'], ['image/png', 'image/jpg', 'image/jpeg']) || count ($extstatus_matches) == 0)
        return \exptrust\left() (['status' => 500, 'msg' => 'File extension is not allowed.']);

    if ($_FILES['file_data']['size'] > 2 * 1024 * 1024) // 2MB
        return \exptrust\left() (['status' => 500, 'msg' => 'File size exceeded.']);

    $target_path = wp_upload_dir () ['basedir'] . '/cs3lider/';
    if (!is_dir ($target_path))
        mkdir ($target_path, 0777);
    $image_name = time () . '_' . preg_replace ('/\.(?=.*\.)/', '_', sanitize_file_name (wp_basename ($name)));
    move_uploaded_file ($_FILES['file_data']['tmp_name'], $target_path . $image_name);
    return \exptrust\right() (['msg' => 'File uploaded successfully.', 'relative_file_path' => '/cs3lider/' . $image_name]);
} );


} ); // plugin_loaded


function html (array $slider) : string {
    $slides = $slider['slides'];
    $indices = range (0, count ($slides) - 1);

    $propagate = \exptrust\curry() (function (callable $f_cs, array $base_names) use ($indices) : array { return
                                        \exptrust\map() (\exptrust\pack2() ($f_cs))
                                                        (\exptrust\cross_product() ($base_names) ($indices)); });

    $radios = $propagate (function (string $name, int $n) : string { return
                              "<input type='radio' id='cs3lider-{$name}{$n}' name='cs3lider'" . ($name == 'r' && $n == 0 ? " checked='checked'>" : '>'); })
                         (['r', 'p', 'n', 'b_r', 'b_p', 'b_n']);

    $lis = \exptrust\zip_with() (\exptrust\curry() (function (int $n, array $slide) : string { return
                                       "<li>\n" . ($slide['url'] != '' ? "<a href='". $slide['url'] . "'>" : '')
                                           . "<img src='" . wp_upload_dir () [ 'baseurl' ] . $slide['img_path'] . "' alt='" . $slide['alt'] . "'>"
                                           . "\n<div class='cs3lider__slides__prev_next'>\n"
                                           . "<label for='cs3lider-p{$n}'></label><label for='cs3lider-n{$n}'></label>\n"
			                               . "<label for='cs3lider-b_p{$n}'></label><label for='cs3lider-b_n{$n}'></label>\n</div>"
			                               . ($slide['url'] != '' ? "</a>" : '') . "</li>"; }))
                                ($indices)
                                ($slides);

    $labels_r = function (int $n) : string { return "<label for='cs3lider-r{$n}'></label><label for='cs3lider-b_r{$n}'></label>"; };

    return
    \exptrust\intercalate() ("\n") (['<div class="cs3lider" id="cs3lider">',
                                     \exptrust\intercalate() ("\n") ($radios),
                                     '<ul class="cs3lider__slides">',
                                     \exptrust\intercalate() ("\n") ($lis),
                                     '</ul>',
                                     '<div class="cs3lider__circles">',
                                     \exptrust\intercalate() ("\n") (\exptrust\map() ($labels_r) ($indices)),
                                     '</div>',
                                     '</div>']); } // html()

function css (array $slider) : string {
    $slides = $slider['slides'];
    $images_count = count ($slides);
    $indices = range (0, count ($slides) - 1);
    $interval = $slider['interval'];
    $propagate = \exptrust\curry() (function (callable $f_cs, array $base_names) use ($indices) : array { return
                                        \exptrust\map() (\exptrust\pack2() ($f_cs))
                                                        (\exptrust\cross_product() ($base_names) ($indices)); });

    $radios = $propagate (function (string $name, int $n) : string { return "#cs3lider-{$name}{$n}"; })
                         (['r', 'p', 'n', 'b_r', 'b_p', 'b_n']);

    $selected_circles = function ($n) use ($images_count) : string { return
                "#cs3lider .cs3lider__slides li:nth-child(" . ($n + 1) . ")::after { right:" . (($images_count - $n - 1) * 14) . "px; }"; };

    $animations = $propagate (function (string $name, int $n) use ($images_count, $interval) : string { return
                                  "#cs3lider-{$name}{$n}:checked ~ .cs3lider__slides { animation:cs3lider-slides_{$name}{$n} " . $images_count * $interval .'ms infinite; }'; })
                             (['r', 'p', 'n', 'b_r', 'b_p', 'b_n']);

    $checked_rs = $propagate (function (string $name, int $n) : string { return "#cs3lider-{$name}{$n}:checked ~ .cs3lider__circles label[for=cs3lider-{$name}{$n}]"; })
                             (['r', 'b_r']);

    $checked_pns = $propagate (function (string $name, int $n) : string { return "#cs3lider-{$name}{$n}:checked ~ .cs3lider__slides label[for=cs3lider-{$name}{$n}]"; })
                              (['p', 'b_p', 'n', 'b_n']);

    $keyframes_ = function (int $offset) use ($images_count, $indices, $propagate) : callable { return
                      $propagate (function (string $name, int $n) use ($images_count, $indices, $offset) : string {
                                      $unit = round (100 / $images_count, 1, PHP_ROUND_HALF_DOWN);
                                      $delta = round ($unit / 17, 1, PHP_ROUND_HALF_DOWN); return
                                      "@keyframes cs3lider-slides_{$name}{$n} {\n"
                                          . \exptrust\intercalate() ("\n")
                                                                    (\exptrust\map() (function (int $i) use ($n, $unit, $delta, $images_count, $offset) : string {
                                                                                          [$fst, $lst] = [$i * $unit, ($i + 1) * $unit];
                                                                                          $left = \exptrust\euclidean_mod() ($n + $i + $offset) ($images_count) * -100; return
                                                                                          ($i == 0 ? 0 : $fst + 0.001) . '%,' . ($i == $images_count - 1 ? 100 : $lst)
                                                                                              . "% { left:{$left}%; opacity:0; }\n" . ($fst + $delta) . '%,'
                                                                                              . ($lst - $delta) . "% { left:{$left}%; opacity:1; }"; } )
                                                                                     ($indices))
                                          . ' }'; }); };

    $keyframes = \exptrust\concat() (\exptrust\zip_with() ($keyframes_) ([0, -1, 1]) ([['r', 'b_r'], ['p', 'b_p'], ['n', 'b_n']]));

    return
    \exptrust\intercalate() ("\n")
                            (['#cs3lider { box-sizing:border-box; display:flex; flex-direction:column; overflow-x:hidden; }',
                              '#cs3lider *, #cs3lider ::after { box-sizing:inherit; padding:0; margin:0; }',
                              \exptrust\intercalate() (',') ($radios) . '{ display:none; }',
                              '#cs3lider .cs3lider__circles { margin-top:2px; display:flex; justify-content:flex-end; }',
                              '#cs3lider .cs3lider__circles > label + label { margin-left:2px; }',
                              '#cs3lider .cs3lider__circles label:nth-child(even) { margin-left:-12px; }',
                              '#cs3lider .cs3lider__circles > label, #cs3lider .cs3lider__slides li::after { background:gray; border-radius:50%; height:12px; width:12px; cursor:pointer; }',
                              '#cs3lider .cs3lider__slides li::after { display:block; content:""; background:black; position:absolute; top:calc(100% + 2px); }',
                              \exptrust\intercalate() ("\n") (\exptrust\map() ($selected_circles) ($indices)),
                              '#cs3lider .cs3lider__slides__prev_next { display:grid; grid-template-areas:"prev next"; justify-content:space-between; align-items:center; position:absolute; top:0%; width:100%; height:100%; }',
                              '#cs3lider .cs3lider__slides__prev_next > label { display:block; width:100px; height:100px; background-color:rgba(255,255,255,0.3); border-radius:50%; position:relative; opacity:0; transition:opacity linear 0.2s; }',
                              '#cs3lider .cs3lider__slides:hover .cs3lider__slides__prev_next > label { opacity:1; }',
                              '#cs3lider .cs3lider__slides__prev_next > label:hover { cursor:pointer; }',
                              '#cs3lider .cs3lider__slides__prev_next > label:nth-child(odd) { grid-area:prev; transform:translate(-30%) scale(0.3); }',
                              '#cs3lider .cs3lider__slides__prev_next > label:nth-child(even) { grid-area:next; transform:translate(30%) scale(0.3); }',
                              '#cs3lider .cs3lider__slides__prev_next > label::after { position:absolute; top:0; display:block; content:""; width:100%; height:100%; border-style:solid; border-width:20px; }',
                              '#cs3lider .cs3lider__slides__prev_next > label:nth-child(odd)::after { border-color:gray transparent transparent gray; transform:scale(0.5) translate(25%) rotate(-45deg); }',
                              '#cs3lider .cs3lider__slides__prev_next > label:nth-child(even)::after { border-color:gray gray transparent transparent; transform:scale(0.5) translate(-25%) rotate(45deg); }',
                              '#cs3lider .cs3lider__slides__prev_next > label:nth-child(odd):hover::after { border-color:black transparent transparent black; }',
                              '#cs3lider .cs3lider__slides__prev_next > label:nth-child(even):hover::after { border-color:black black transparent transparent; }',
                              '#cs3lider .cs3lider__slides { list-style-type:none; padding-left:0; display:flex; position:relative; width:100%; }',
                              '#cs3lider .cs3lider__slides li { position:relative; width:100%; flex:0 0 100%; }',
                              '#cs3lider .cs3lider__slides img { width:100%; height:100%; object-fit:cover; }',
                              \exptrust\intercalate() ("\n") ($animations),
                              \exptrust\intercalate() (",") (\exptrust\cat() ($checked_rs) ($checked_pns)) . '{ display:none; }',
                              \exptrust\intercalate() ("\n") ($keyframes)]); }