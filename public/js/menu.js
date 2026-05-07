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
document.addEventListener('DOMContentLoaded', function() {
  const toggleBtn = document.getElementById('sidebar-toggle-btn');
  const sidebar = document.getElementById('sidebar');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function() {
      sidebar.classList.toggle('active');
      updateSidebarIcon();
    });
  } else {
    console.warn('sidebar-toggle-btn または sidebar 要素が見つかりません。');
  }
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
          // menuIdが有効かどうかチェック
          if (typeof menuId !== 'string' || menuId.trim() === '') {
            console.warn('Invalid menuId:', menuId);
            continue; // menuIdが不正な場合はスキップ
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
    