#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <unistd.h>
#include <arpa/inet.h>
#include <sys/types.h>
#include <poll.h>
#include <sys/epoll.h>

#define BUF_SIZE 200

int main(int argc, char *argv[])
{
	int sock_fd, conn_fd;
	struct sockaddr_in server_addr;
	char buff[BUF_SIZE];
	int ret;
	int port_number = 2047;

	// 创建socket描述符
	if ((sock_fd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
		fprintf(stderr,"Socket error:%s\n\a", strerror(errno));
		exit(1);
	}

	// 填充sockaddr_in结构
	bzero(&server_addr, sizeof(struct sockaddr_in));
	server_addr.sin_family = AF_INET;
	server_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	server_addr.sin_port = htons(port_number);

	// 绑定sock_fd描述符
	if (bind(sock_fd, (struct sockaddr *)(&server_addr), sizeof(struct sockaddr)) == -1) {
		fprintf(stderr,"Bind error:%s\n\a", strerror(errno));
		exit(1);
	}

	// 监听sock_fd描述符
	if(listen(sock_fd, 5) == -1) {
		fprintf(stderr,"Listen error:%s\n\a", strerror(errno));
		exit(1);
	}

	int i;
	int conn_num = 5; //连接数
	int timeout = 3000; //超时时间
	struct epoll_event eventList[conn_num]; //事件数组

	// epoll创建并初始化
	int epollfd = epoll_create(conn_num);

	// 将socket描述符添加到epoll监听
	struct epoll_event event;
	event.events = EPOLLIN|EPOLLET; // 可读和边缘触发（通知一次，不管有没有处理）
	event.data.fd = sock_fd;
	if(epoll_ctl(epollfd, EPOLL_CTL_ADD, sock_fd, &event) < 0) {
		printf("epoll add fail : fd = %d\n", sock_fd);
		exit(1);
	}

	while(1) {

		// 获取epoll监听中活跃的描述符
		int active_num = epoll_wait(epollfd, eventList, conn_num, timeout);
		printf ( "active_num: %d\n", active_num);
		if(active_num < 0) {
			printf("epoll wait error\n");
			break;
		} else if(active_num == 0) {
			printf("timeout ...\n");
			continue;
		}


		//直接获取了事件数量,给出了活动的流,这里是和poll区别的关键
		for(i = 0; i < active_num; i++) {

			// 非可读跳过
			if (!(eventList[i].events & EPOLLIN)) {
				printf ( "event: %d\n", eventList[i].events);
				continue;
			}

			// 判断是否新连接
			if (eventList[i].data.fd == sock_fd) {
				conn_fd = accept(sock_fd, (struct sockaddr *)NULL, NULL);

				if (conn_fd < 0) {
					printf("accept error\n");
					continue;
				}
				printf("Accept Connection: %d\n", conn_fd);

				//将新建立的连接添加到epoll的监听中
				struct epoll_event event;
				event.data.fd = conn_fd;
				event.events =  EPOLLIN|EPOLLET;
				epoll_ctl(epollfd, EPOLL_CTL_ADD, conn_fd, &event);
			} else {

				// 接受数据
				ret = recv(eventList[i].data.fd, buff, BUF_SIZE, 0);
				if (ret <= 0) {
					// 客户端关闭
					printf("client[%d] close\n", i);
					close(eventList[i].data.fd);

					// 删除监听
					epoll_ctl(epollfd, EPOLL_CTL_DEL, eventList[i].data.fd, NULL);
				} else {

					// 添加结束符
					if (ret < BUF_SIZE) {
						memset(&buff[ret], '\0', 1);
					}
					printf("client[%d] send:%s\n", i, buff);

					// 发送数据
					send(eventList[i].data.fd, "Hello", 6, 0);
				}
			}
		}
	}

	// 关闭连接
	close(epollfd);
	close(sock_fd);
	exit(0);
}