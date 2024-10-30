/* global jQuery, CS3liderAdmin */

jQuery ($ => {
    const $form = $ ('#cs3lider-form');
    const $msg = $ ('#cs3lider-message', $form);
    const make_default_slide = img => ({ img_path: img, alt: img .match (/^\/cs3lider\/\d+_(.+)\.(png|jpg|jpeg)$/)[1], url: '' });
    const set_slide_alt = newalt => slide => ({ img_path: slide .img_path, alt: newalt, url: slide .url });
    const set_slide_url = newurl => slide => ({ img_path: slide .img_path, alt: slide .alt, url: newurl });
    const make_slide_li = slide => $ ('<li class="cs3lider-admin__slide">')
        .data ('img_path', slide .img_path)
        .append ([ $ ('<img>', { src: '/wp-content/uploads' + slide .img_path }), $ ('<br>'),
                   $ ('<label>') .text ('Title:') .append ($ ('<input>', { type: 'text', size: 80, value: slide .alt })), $ ('<br>'),
                   $ ('<label>') .text ('Link:') .append ($ ('<input>', { type: 'text', size: 80, value: slide .url })), $ ('<br>'),
                   $ ('<input>', { type: 'button', name: 'cs3lider-delete-slide', value: 'Delete this slide', 'class': 'button button-secondary' })]);

    $ .getJSON (CS3liderAdmin.ajax + 'get_slider') .done (slider => $form
        .find ('#cs3lider-admin-slides')
            .append (slider .slides .map (make_slide_li))
            .on ('click', 'input[type="button"][name="cs3lider-delete-slide"]', e => $ (e .target) .closest ('li') .remove ())
            .end ()
        .find ('#cs3lider-interval') .val (slider.interval)
    ) .fail (jqXHR => console .log ('server side error'));

    $ ('#cs3lider-add-slide') .click (e => {
        $msg .removeClass ('cs3lider-message-error') .removeClass ('cs3lider-message-success');
        if ($ ('#cs3lider-slide-image') .val () == '') {
            $msg .html ('filie is required<br/>') .addClass ('cs3lider-message-error') .show ();
            return;
        }
        const formData = new FormData ();
        formData .append ('file_nonce', CS3liderAdmin .nonce);
        formData .append ('file_data', $ ('#cs3lider-slide-image') .prop ('files') [0]);
        $ .post ({
            url: CS3liderAdmin .ajax + 'add_image',
            data:  formData,
            mimeType: 'multipart/form-data',
            contentType: false,
            cache: false,
            dataType: 'json',
            processData: false
        }) .done (json => $form
            .find ('#cs3lider-slide-image') .val ('') .end ()
            .find ('#cs3lider-admin-slides') .append (make_slide_li (make_default_slide (json .relative_file_path)))
        ) .fail (jqXHR => $msg .html (jqXHR .responseJSON .msg) .removeClass ('cs3lider-message-success') .addClass ('cs3lider-message-error') .show ());
    });

    $ ('#cs3lider-save-slider') .click (e =>
        $ .post({
            url: CS3liderAdmin .ajax + 'save_slider',
            contentType: 'application/json',
            dataType: "json",
            data: JSON .stringify ({
                nonce: CS3liderAdmin .nonce,
                sliderinfo : {
                    slides: $ ('#cs3lider-admin-slides')
                                .children ()
                                .map ((i, e) =>
                                    set_slide_url ($ ('label:nth-of-type(2) input', e) .val ())
                                                  (set_slide_alt ($ ('label:nth-of-type(1) input', e) .val ()) (make_default_slide ($ (e) .data ('img_path'))))
                                ) .toArray (),
                    interval: $ ('#cs3lider-interval') .val ()
                }
            })
        }) .done (json => $msg .html (json .msg) .removeClass ('cs3lider-message-error') .addClass ('cs3lider-message-success') .show ())
           .fail (jqXHR => $msg .html (jqXHR .responseJSON .msg) .removeClass ('cs3lider-message-success') .addClass ('cs3lider-message-error') .show ())
    );
});