## socket 和 sock
实际上，对每一个新创建的套接字，内核协议栈都会创建struct socket和struct sock两个数据结构。这两个结构就像孪生兄弟，struct socket面向用户空间，struct sock面向内核空间。


https://blog.csdn.net/lrjxgl/article/details/94502632
