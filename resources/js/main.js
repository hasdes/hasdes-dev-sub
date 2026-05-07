
(function() {
    "use strict";
  
    /**
     * Easy selector helper function
     */
    const select = (el, all = false) => {
      el = el.trim();
      if (all) {
        return [...document.querySelectorAll(el)];
      } else {
        return document.querySelector(el);
      }
    };
  
    /**
     * Easy event listener function
     */
    const on = (type, el, listener, all = false) => {
      if (all) {
        select(el, all).forEach(e => e.addEventListener(type, listener));
      } else {
        select(el, all).addEventListener(type, listener);
      }
    };
  
    /**
     * Easy on scroll event listener 
     */
    const onscroll = (el, listener) => {
      el.addEventListener('scroll', listener);
    };
  
    /**
     * Sidebar toggle
     */
    if (select('.toggle-sidebar-btn')) {
      on('click', '.toggle-sidebar-btn', function(e) {
        select('body').classList.toggle('toggle-sidebar');
      });
    }
  
    /**
     * Search bar toggle
     */
    if (select('.search-bar-toggle')) {
      on('click', '.search-bar-toggle', function(e) {
        select('.search-bar').classList.toggle('search-bar-show');
      });
    }
  
    /**
     * Navbar links active state on scroll
     */
    let navbarlinks = select('#navbar .scrollto', true);
    const navbarlinksActive = () => {
      let position = window.scrollY + 200;
      navbarlinks.forEach(navbarlink => {
        if (!navbarlink.hash) return;
        let section = select(navbarlink.hash);
        if (!section) return;
        if (position >= section.offsetTop && position <= (section.offsetTop + section.offsetHeight)) {
          navbarlink.classList.add('active');
        } else {
          navbarlink.classList.remove('active');
        }
      });
    };
    window.addEventListener('load', navbarlinksActive);
    onscroll(document, navbarlinksActive);
  
    /**
     * Toggle .header-scrolled class to #header when page is scrolled
     */
    let selectHeader = select('#header');
    if (selectHeader) {
      const headerScrolled = () => {
        if (window.scrollY > 100) {
          selectHeader.classList.add('header-scrolled');
        } else {
          selectHeader.classList.remove('header-scrolled');
        }
      };
      window.addEventListener('load', headerScrolled);
      onscroll(document, headerScrolled);
    }
  
    /**
     * Back to top button
     */
    let backtotop = select('.back-to-top');
    if (backtotop) {
      const toggleBacktotop = () => {
        if (window.scrollY > 100) {
          backtotop.classList.add('active');
        } else {
          backtotop.classList.remove('active');
        }
      };
      window.addEventListener('load', toggleBacktotop);
      onscroll(document, toggleBacktotop);
    }
  
    /**
     * Prevent dropdown from closing when clicked inside
     */
    document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
      dropdown.addEventListener('click', function (e) {
        e.stopPropagation();
      });
    });
  
    /**
     * Custom code to prevent dropdowns from closing
     */
    document.querySelectorAll('.dropdown').forEach(dropdown => {
      dropdown.addEventListener('hide.bs.dropdown', function(e) {
        if (e.clickEvent && e.clickEvent.target.closest('.dropdown-menu')) {
          e.preventDefault();
        }
      });
    });
  
    /**
     * Keep dropdowns open when clicking another dropdown
     */
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
      dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdowns.forEach(otherDropdown => {
          if (otherDropdown !== dropdown) {
            const dropdownMenu = otherDropdown.querySelector('.dropdown-menu');
            if (dropdownMenu.classList.contains('show')) {
              dropdownMenu.classList.remove('show');
              otherDropdown.classList.remove('show');
            }
          }
        });
        const dropdownMenu = dropdown.querySelector('.dropdown-menu');
        if (!dropdownMenu.classList.contains('show')) {
          dropdownMenu.classList.add('show');
          dropdown.classList.add('show');
        }
      });
    });
  
    /**
     * Initiate tooltips
     */
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  
    /**
     * Initiate quill editors
     */
    if (select('.quill-editor-default')) {
      new Quill('.quill-editor-default', {
        theme: 'snow'
      });
    }
  
    if (select('.quill-editor-bubble')) {
      new Quill('.quill-editor-bubble', {
        theme: 'bubble'
      });
    }
  
    if (select('.quill-editor-full')) {
      new Quill(".quill-editor-full", {
        modules: {
          toolbar: [
            [{
              font: []
            }, {
              size: []
            }],
            ["bold", "italic", "underline", "strike"],
            [{
                color: []
              },
              {
                background: []
              }
            ],
            [{
                script: "super"
              },
              {
                script: "sub"
              }
            ],
            [{
                list: "ordered"
              },
              {
                list: "bullet"
              },
              {
                indent: "-1"
              },
              {
                indent: "+1"
              }
            ],
            ["direction", {
              align: []
            }],
            ["link", "image", "video"],
            ["clean"]
          ]
        },
        theme: "snow"
      });
    }
  
    /**
     * Initiate TinyMCE Editor
     */
    const useDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isSmallScreen = window.matchMedia('(max-width: 1023.5px)').matches;
  
    tinymce.init({
      selector: 'textarea.tinymce-editor',
      plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons accordion',
      editimage_cors_hosts: ['picsum.photos'],
      menubar: 'file edit view insert format tools table help',
      toolbar: "undo redo | accordion accordionremove | blocks fontfamily fontsize | bold italic underline strikethrough | align numlist bullist | link image | table media | lineheight outdent indent| forecolor backcolor removeformat | charmap emoticons | code fullscreen preview | save print | pagebreak anchor codesample | ltr rtl",
      autosave_ask_before_unload: true,
      autosave_interval: '30s',
      autosave_prefix: '{path}{query}-{id}-',
      autosave_restore_when_empty: false,
      autosave_retention: '2m',
      image_advtab: true,
      link_list: [{
          title: 'My page 1',
          value: 'https://www.tiny.cloud'
        },
        {
          title: 'My page 2',
          value: 'http://www.moxiecode.com'
        }
      ],
      image_list: [{
          title: 'My page 1',
          value: 'https://www.tiny.cloud'
        },
        {
          title: 'My page 2',
          value: 'http://www.moxiecode.com'
        }
      ],
      image_class_list: [{
          title: 'None',
          value: ''
        },
        {
          title: 'Some class',
          value: 'class-name'
        }
      ],
      importcss_append: true,
      file_picker_callback: (callback, value, meta) => {
        /* Provide file and text for the link dialog */
        if (meta.filetype === 'file') {
          callback('https://www.google.com/logos/google.jpg', {
            text: 'My text'
          });
        }
  
        /* Provide image and alt text for the image dialog */
        if (meta.filetype === 'image') {
          callback('https://www.google.com/logos/google.jpg', {
            alt: 'My alt text'
          });
        }
  
        /* Provide alternative source and posted for the media dialog */
        if (meta.filetype === 'media') {
          callback('movie.mp4', {
            source2: 'alt.ogg',
            poster: 'https://www.google.com/logos/google.jpg'
          });
        }
      },
      height: 600,
      image_caption: true,
      quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
      noneditable_class: 'mceNonEditable',
      toolbar_mode: 'sliding',
      contextmenu: 'link image table',
      skin: useDarkMode ? 'oxide-dark' : 'oxide',
      content_css: useDarkMode ? 'dark' : 'default',
      content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px }'
    });
  
    /**
     * Initiate Bootstrap validation check
     */
    var needsValidation = document.querySelectorAll('.needs-validation');
  
    Array.prototype.slice.call(needsValidation)
      .forEach(function(form) {
        form.addEventListener('submit', function(event) {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
  
          form.classList.add('was-validated');
        }, false);
      });
  
    /**
     * Initiate Datatables
     */
    const datatables = select('.datatable', true);
    datatables.forEach(datatable => {
      new simpleDatatables.DataTable(datatable, {
        perPageSelect: [5, 10, 15, ["All", -1]],
        columns: [{
            select: 2,
            sortSequence: ["desc", "asc"]
          },
          {
            select: 3,
            sortSequence: ["desc"]
          },
          {
            select: 4,
            cellClass: "green",
            headerClass: "red"
          }
        ]
      });
    });
  
    /**
     * Autoresize echart charts
     */
    const mainContainer = select('#main');
    if (mainContainer) {
      setTimeout(() => {
        new ResizeObserver(function() {
          select('.echart', true).forEach(getEchart => {
            echarts.getInstanceByDom(getEchart).resize();
          });
        }).observe(mainContainer);
      }, 200);
    }
  
  })();
  
  
  // メニューバーをPC画面は矢印、スマホ画面は三本バーに設定する処理
function updateSidebarIcon() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    const isMobile = window.innerWidth <= 1199; // モバイルデバイスの幅
  
    if (sidebar.classList.contains('active')) {
      // メニューが開いているとき
      toggleBtn.classList.remove('bi-arrow-left-short', 'bi-list');
      toggleBtn.classList.add(isMobile ? 'bi-list' : 'bi-arrow-right-short');
    } else {
      // メニューが閉じているとき
      toggleBtn.classList.remove('bi-arrow-right-short', 'bi-list');
      toggleBtn.classList.add(isMobile ? 'bi-list' : 'bi-arrow-left-short');
    }
  }
  // 初期アイコンの設定
  document.addEventListener('DOMContentLoaded', updateSidebarIcon);
  // ウィンドウのリサイズ時にアイコンを更新
  window.addEventListener('resize', updateSidebarIcon);
  // メニューボタンのクリック時にアイコンを切り替える
  document.getElementById('sidebar-toggle-btn').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
    updateSidebarIcon();
  });
  // 検索条件のアコーディオン
  document.addEventListener('DOMContentLoaded', function() {
    const detailsElements = document.querySelectorAll('.contents_head');
    detailsElements.forEach(detailsElement => {
      const iconElement = detailsElement.querySelector('summary i');
      function updateIcon() {
        if (detailsElement.open) {
          iconElement.classList.remove('bi-arrow-down');
          iconElement.classList.add('bi-arrow-up');
        } else {
          iconElement.classList.remove('bi-arrow-up');
          iconElement.classList.add('bi-arrow-down');
        }
      }
      // 初期アイコンの設定
      updateIcon();
      // 開閉時にアイコンを更新
      detailsElement.addEventListener('toggle', updateIcon);
    });
  });
      // ドロップダウンアイコンを切り替える処理
      document.addEventListener('DOMContentLoaded', function () {
        const detailsElements = document.querySelectorAll('#sidebar-nav details');
        detailsElements.forEach(details => {
          const icon = details.querySelector('summary .toggle-icon');
          // 初期アイコンの設定
          updateIcon(details, icon);  
          details.addEventListener('toggle', function () {
            updateIcon(details, icon);
          });
        });
        function updateIcon(details, icon) {
          if (details.open) {
            icon.classList.remove('bi-chevron-down');
            icon.classList.add('bi-chevron-up');
          } else {
            icon.classList.remove('bi-chevron-up');
            icon.classList.add('bi-chevron-down');
          }
        }
      });
  
  // プルダウンメニューを開きっぱなしにする動き
      document.addEventListener("DOMContentLoaded", function () {
        // メニューの開閉状態をsessionStorageから読み込む
        const menuState = sessionStorage.getItem("menuState");
        
        if (menuState) {
          const state = JSON.parse(menuState);
          for (const [menuId, isOpen] of Object.entries(state)) {
            const menu = document.querySelector(menuId);
            if (menu && isOpen) {
              menu.setAttribute('open', 'open'); // メニューを開く
            }
          }
        }
      
        // 各detailsタグにイベントリスナーを追加して、メニューの開閉状態を保存する
        document.querySelectorAll("details").forEach(details => {
          details.addEventListener("toggle", function () {
            saveMenuState();
          });
        });
      
        // メニューの状態を保存する関数
        function saveMenuState() {
          const menuState = {};
          document.querySelectorAll("details").forEach(details => {
            menuState[`#${details.id}`] = details.hasAttribute('open');
          });
          sessionStorage.setItem("menuState", JSON.stringify(menuState));
        }
      
        // ページ遷移時にメニューの状態を維持する
        window.addEventListener("beforeunload", function () {
          saveMenuState();
        });
      });
  
      document.addEventListener("DOMContentLoaded", function () {
        // ページ読み込み時に、localStorageからメニュー状態を取得し開く
        const openMenuId = sessionStorage.getItem("openMenuId");
        if (openMenuId) {
          const menu = document.getElementById(openMenuId);
          if (menu) {
            menu.setAttribute('open', 'open'); // 対象メニューを開く
          }
        }
      
        // 各detailsタグにクリックイベントを追加して、選択したメニューを記憶
        document.querySelectorAll("details").forEach(details => {
          details.addEventListener("toggle", function () {
            if (details.hasAttribute('open')) {
              // 開いたメニューをsessionStorageに保存
              sessionStorage.setItem("openMenuId", details.id);
            }
          });
        });
      });
      
      document.addEventListener("DOMContentLoaded", function () {
        // ページ読み込み時にプルダウンの状態をリセット
        const currentPage = window.location.pathname;
        const savedPage = sessionStorage.getItem("currentPage");
        
        // ページが異なればプルダウンメニューを閉じ、ページの状態を更新
        if (savedPage !== currentPage) {
          sessionStorage.removeItem("openMenuId"); // メニューの状態をリセット
        }
      
        // 現在のページを保存
        sessionStorage.setItem("currentPage", currentPage);
      
        // 保存されたメニューIDを取得して開く
        const openMenuId = sessionStorage.getItem("openMenuId");
        if (openMenuId) {
          const menu = document.getElementById(openMenuId);
          if (menu) {
            menu.setAttribute('open', 'open'); // 保存されたメニューを開く
          }
        }
      
        // メニューをクリックしたときにIDを保存する
        document.querySelectorAll("details").forEach(details => {
          details.addEventListener("toggle", function () {
            if (details.hasAttribute('open')) {
              // 開いたメニューのIDを保存
              sessionStorage.setItem("openMenuId", details.id);
            } else {
              // 閉じた場合、IDを削除
              sessionStorage.removeItem("openMenuId");
            }
          });
        });
      });
      
  