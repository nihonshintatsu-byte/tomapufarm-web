/*
 * お問い合わせフォーム（/contact/）と注文フォーム（/form/）の共通スクリプト。
 * 送信先は form の action（既定: /api/contact.php）。送信できたら /contact/thanks/ へ移動します。
 */
(function () {
  'use strict';

  var THANKS_URL = '/contact/thanks/';

  function showError(form, message) {
    var boxes = form.querySelectorAll('.form-error, .form-error-tip');
    for (var i = 0; i < boxes.length; i++) {
      var visible = !boxes[i].closest('[hidden]');
      boxes[i].textContent = visible ? message : '';
      boxes[i].hidden = !visible;
    }
    // 見えている枠がない場合の保険
    var any = form.querySelector('.form-error:not([hidden]), .form-error-tip:not([hidden])');
    if (!any && boxes.length) {
      boxes[boxes.length - 1].textContent = message;
      boxes[boxes.length - 1].hidden = false;
    }
  }

  function clearError(form) {
    var boxes = form.querySelectorAll('.form-error, .form-error-tip');
    for (var i = 0; i < boxes.length; i++) { boxes[i].textContent = ''; boxes[i].hidden = true; }
  }

  /** 必須項目が埋まっているか。埋まっていなければエラー文を返します。 */
  function validate(scope) {
    var fields = scope.querySelectorAll('[required]');
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      if (f.type === 'checkbox' && !f.checked) return 'プライバシーポリシーへの同意が必要です。';
      if (f.type !== 'checkbox' && !String(f.value).trim()) return '未入力の必須項目があります。';
      if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.value)) return 'メールアドレスの形式をご確認ください。';
    }
    return '';
  }

  /** dl 単位で「項目名 → 入力値」を取り出します（確認画面用） */
  function collectRows(form) {
    var rows = [];
    var dls = form.querySelectorAll('dl[data-label]');
    for (var i = 0; i < dls.length; i++) {
      var dl = dls[i];
      var label = dl.getAttribute('data-label');
      var values = [];
      var inputs = dl.querySelectorAll('input, textarea, select');
      for (var j = 0; j < inputs.length; j++) {
        var el = inputs[j];
        if (el.type === 'radio' || el.type === 'checkbox') {
          if (el.checked) values.push(el.value);
        } else if (String(el.value).trim()) {
          values.push(el.value);
        }
      }
      rows.push({ label: label, value: values.join('、') });
    }
    return rows;
  }

  function renderConfirm(form) {
    var table = form.querySelector('[data-confirm-table]');
    if (!table) return;
    var rows = collectRows(form);
    var html = '';
    for (var i = 0; i < rows.length; i++) {
      var v = rows[i].value ? rows[i].value : '（未入力）';
      html += '<dl class="form-table__row"><dt><span class="title">' + esc(rows[i].label) + '</span></dt>' +
              '<dd><p class="confirm-value">' + esc(v).replace(/\n/g, '<br>') + '</p></dd></dl>';
    }
    table.innerHTML = html;
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function step(form, name) {
    var steps = form.querySelectorAll('.form-step');
    for (var i = 0; i < steps.length; i++) {
      steps[i].hidden = steps[i].getAttribute('data-step') !== name;
    }
    var anchor = document.getElementById('Sec01');
    if (anchor) window.scrollTo(0, anchor.offsetTop - 80);
  }

  function send(form) {
    clearError(form);
    var submit = form.querySelector('[data-action="send"], input[type="submit"]');
    if (submit) { submit.disabled = true; submit.value = '送信中…'; }

    var data = new FormData(form);
    data.append('form-name', form.getAttribute('data-form-name') || 'お問い合わせ');
    data.append('page-url', location.href);

    fetch(form.getAttribute('action'), { method: 'POST', body: data })
      .then(function (res) { return res.json().catch(function () { return { ok: res.ok }; }); })
      .then(function (json) {
        if (json && json.ok) {
          location.href = THANKS_URL;
        } else {
          throw new Error((json && json.error) || '送信に失敗しました。');
        }
      })
      .catch(function (err) {
        if (submit) { submit.disabled = false; submit.value = '送信する'; }
        showError(form, err.message + '　お急ぎの場合はお電話（0134-65-8077）でご連絡ください。');
      });
  }

  function init(form, hasConfirmStep) {
    var opened = form.querySelector('[name="form-opened-at"]');
    if (opened) opened.value = String(Date.now());

    if (hasConfirmStep) {
      form.addEventListener('click', function (e) {
        var action = e.target && e.target.getAttribute && e.target.getAttribute('data-action');
        if (action === 'confirm') {
          var input = form.querySelector('[data-step="input"]');
          var message = validate(input);
          if (message) { showError(form, message); return; }
          clearError(form);
          renderConfirm(form);
          step(form, 'confirm');
        } else if (action === 'back') {
          clearError(form);
          step(form, 'input');
        }
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var message = validate(form);
      if (message) { showError(form, message); return; }
      send(form);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var contact = document.getElementById('ContactFormEl');
    if (contact) init(contact, true);
    var order = document.getElementById('OrderFormEl');
    if (order) init(order, false);
  });
})();
