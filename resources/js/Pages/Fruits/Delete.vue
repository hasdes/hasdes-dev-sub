<script setup>

import axios from 'axios';
import {ElNotification,ElMessageBox } from 'element-plus';
// import {defineEmits} from 'vue';

const emit = defineEmits(['reLoad']);

const open=(item)=>{
  
        ElMessageBox.confirm(
          item.name+'を削除します。よろしいですか?',
          'Warning',
          {
            confirmButtonText: 'OK',
            cancelButtonText: 'Cancel',
            type: 'warning',
          }
        )
          .then(() => {
            axios.delete('/api/fruits/delete/'+item.id)
              .then((res)=>{
                ElNotification({
                  type: 'success',
                  message: item.name+'を削除しました',
                });
                emit('reLoad');
              }).catch((error)=>{
                ElNotification({
                  type: 'error',
                  message: item.name+'の削除に失敗しました',
                });
              })
          })
          .catch(() => {
            ElNotification({
              type: 'info',
              message: '削除をキャンセルしました',
            });
          });
}

defineExpose({
  open,
})
</script>
<template></template>
