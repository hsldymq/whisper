#CHANGELOG

* 0.1.4 (2019-07-05)
    * 修复worker进程无用资源未释放的问题

* 0.1.3 (2019-07-04)
    * 修复worker异常退出时错误的执行了父进程的代码

* 0.1.2 (2019-04-01)
    * 修复当设置了process timer后从event loop抛出错误未移除timer,造成对后续event loop的影响

* 0.1.1 (2019-03-28)
    * 增加操作process timer的方法